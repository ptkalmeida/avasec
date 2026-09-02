<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Support\BusinessRules;
use App\Support\Identity;
use App\Support\InstructorScope;
use Carbon\CarbonImmutable;

/**
 * Emissão e consulta de certificados — espelha src/server/services/certificateService.ts.
 * A emissão NUNCA confia no percentual do cliente: recalcula a frequência a partir do
 * curso + progresso reais. Idempotente por (studentName, courseId).
 */
final class CertificateService
{
    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listCertificates(array $requester, int $skip, int $take): array
    {
        // Aluno vê só os próprios; instrutor só os dos cursos que leciona; admin, todos.
        // Antes o instrutor listava todos os certificados da plataforma.
        $base = Certificate::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($base, $requester);
        } elseif ($requester['role'] === 'instructor') {
            $base->whereIn('courseId', InstructorScope::courseIds($requester));
        }

        $total = (clone $base)->count();
        $items = $base->orderBy('issueDate', 'desc')->skip($skip)->take($take)->get()
            ->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Consulta pública de verificação (id, hash ou nome). Resposta em whitelist:
     * rota sem autenticação nunca expõe identificadores internos (userId/enrollmentId).
     *
     * @return array<string, mixed>|null
     */
    public function verifyCertificatePublic(string $query): ?array
    {
        $trimmed = trim($query);
        $cert = Certificate::query()
            ->where('id', $trimmed)
            ->orWhere('verificationHash', $trimmed)
            ->orWhere('studentName', 'like', '%'.$trimmed.'%')
            ->first();
        if ($cert === null) {
            return null;
        }

        $cargaHoraria = Course::query()->find($cert->courseId)?->cargaHoraria;

        return [
            'id' => $cert->id,
            'studentName' => $cert->studentName,
            'courseTitle' => $cert->courseTitle,
            // Acesso direto devolve Carbon (cast date) — formata explicitamente
            // porque este array não passa pela serialização do model.
            'issueDate' => $cert->issueDate?->format('d/m/Y'),
            'attendancePercent' => $cert->attendancePercent,
            'verificationHash' => $cert->verificationHash,
            'cargaHoraria' => is_numeric($cargaHoraria) ? (int) $cargaHoraria : null,
        ];
    }

    /**
     * @param  array{userId?:string|null,courseId:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function issueCertificate(array $input, array $requester): array
    {
        $userId = Identity::resolveActorUserId($requester, $input['userId'] ?? null);

        $course = Course::query()->with(['lessons', 'liveSessions'])->find($input['courseId']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        // Instrutor emite certificado apenas nos cursos que leciona — a mesma regra
        // que CertificatePdfService já aplica no download. Faltava aqui: quem podia
        // baixar só o próprio conseguia EMITIR para qualquer curso da escola.
        // Para o aluno o risco já era contido (a frequência é recalculada abaixo).
        if ($requester['role'] === 'instructor'
            && ! in_array($course->id, InstructorScope::courseIds($requester), true)) {
            throw ApiException::forbidden('Você só pode emitir certificados dos seus cursos.');
        }

        // Idempotência: se já existe certificado para aluno+curso, apenas retorna.
        $existing = Certificate::query()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->first();
        if ($existing !== null) {
            return $existing->toArray();
        }

        $progress = StudentProgress::query()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->first();

        $attendancePercent = $this->computeAttendancePercent($course, $progress);
        $minAttendance = BusinessRules::courseMinAttendance($course->minAttendance);

        if ($attendancePercent < $minAttendance) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para emissão do certificado ({$attendancePercent}% de {$minAttendance}% exigidos).");
        }

        $enrollmentId = StudentEnrollment::query()->whereKey($userId)->value('id');

        $hashHex = strtoupper(bin2hex(random_bytes(8)));
        $certificate = Certificate::query()->create([
            'id' => "cert-{$course->id}-{$hashHex}",
            'studentName' => Identity::displayName($userId, $requester),
            'userId' => $userId,
            'enrollmentId' => $enrollmentId,
            'courseId' => $course->id,
            'courseTitle' => $course->title,
            // Coluna DATE — o cast do model serializa como d/m/Y no contrato.
            'issueDate' => CarbonImmutable::now(),
            'attendancePercent' => $attendancePercent,
            'verificationHash' => "AVA-{$hashHex}",
        ]);

        return $certificate->toArray();
    }

    public function deleteCertificate(string $id): void
    {
        Certificate::query()->where('id', $id)->delete();
    }

    private function computeAttendancePercent(Course $course, ?StudentProgress $progress): int
    {
        $totalActivities = $course->lessons->count() + $course->liveSessions->count();
        if ($totalActivities === 0) {
            return 0;
        }
        // Conta apenas ids que AINDA existem no curso. Resíduo de aula apagada
        // ficava em `completedLessons` e era contado como presença — e esta é a
        // conta que decide a EMISSÃO do certificado, não um número de tela.
        $aulas = is_array($progress?->completedLessons) ? $progress->completedLessons : [];
        $encontros = is_array($progress?->attendedLiveSessions) ? $progress->attendedLiveSessions : [];
        $done = count(array_intersect(
            array_values(array_filter($aulas, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->lessons->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        )) + count(array_intersect(
            array_values(array_filter($encontros, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->liveSessions->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        ));

        return min(100, (int) round(($done / $totalActivities) * 100));
    }
}
