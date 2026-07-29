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
        $base = Certificate::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($base, $requester);
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
            'issueDate' => $cert->issueDate,
            'attendancePercent' => $cert->attendancePercent,
            'verificationHash' => $cert->verificationHash,
            'cargaHoraria' => is_numeric($cargaHoraria) ? (int) $cargaHoraria : null,
        ];
    }

    /**
     * @param  array{studentName:string,courseId:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function issueCertificate(array $input, array $requester): array
    {
        if ($requester['role'] === 'student' && $input['studentName'] !== $requester['name']) {
            throw ApiException::forbidden('Você só pode emitir certificado para si mesmo.');
        }

        $course = Course::query()->with(['lessons', 'liveSessions'])->find($input['courseId']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        // Idempotência: se já existe certificado para aluno+curso, apenas retorna.
        $existing = Certificate::query()
            ->where('studentName', $input['studentName'])
            ->where('courseId', $input['courseId'])
            ->first();
        if ($existing !== null) {
            return $existing->toArray();
        }

        $progress = StudentProgress::query()
            ->where('studentName', $input['studentName'])
            ->where('courseId', $input['courseId'])
            ->first();

        $attendancePercent = $this->computeAttendancePercent($course, $progress);
        $minAttendance = BusinessRules::courseMinAttendance($course->minAttendance);

        if ($attendancePercent < $minAttendance) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para emissão do certificado ({$attendancePercent}% de {$minAttendance}% exigidos).");
        }

        $userId = Identity::resolveStudentUserId($input['studentName'], $requester);
        $enrollmentId = StudentEnrollment::query()->where('studentName', $input['studentName'])->value('id');

        $hashHex = strtoupper(bin2hex(random_bytes(8)));
        $certificate = Certificate::query()->create([
            'id' => "cert-{$course->id}-{$hashHex}",
            'studentName' => $input['studentName'],
            'userId' => $userId,
            'enrollmentId' => $enrollmentId,
            'courseId' => $course->id,
            'courseTitle' => $course->title,
            'issueDate' => CarbonImmutable::now()->format('d/m/Y'),
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
        $done = count($progress->completedLessons ?? []) + count($progress->attendedLiveSessions ?? []);

        return min(100, (int) round(($done / $totalActivities) * 100));
    }
}
