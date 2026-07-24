<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AdmissionRequest;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Support\BusinessRules;
use App\Support\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Progresso, matrícula (StudentEnrollment) e admissões (AdmissionRequest) — espelha
 * src/server/services/enrollmentService.ts. Aluno só vê/edita os próprios dados;
 * instrutor fica restrito aos cursos que leciona; admin é irrestrito. Autorização
 * FK-first com fallback por nome (Identity). Penalidade de cancelamento só quando a
 * flag penalidadesCancelamento está ligada — o cliente nunca decide dias/penalidade.
 *
 * $requester = ['sub'=>id, 'name'=>..., 'role'=>...].
 */
final class EnrollmentService
{
    private const EMPTY_ENROLLMENT = [
        'enrolledCourseId' => null,
        'enrolledAt' => null,
        'completedCourseIds' => [],
        'dropOutPenaltyUntil' => null,
    ];

    /** @param array{sub:string,name:mixed,role:mixed} $requester @return string[] */
    private function instructorCourseIds(array $requester): array
    {
        return Course::query()
            ->where('instructorId', $requester['sub'])
            ->orWhere(fn ($q) => $q->whereNull('instructorId')->where('instructorName', $requester['name']))
            ->pluck('id')->all();
    }

    // ---------- PROGRESSO ----------

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function getProgress(?string $requestedStudentName, array $requester): array
    {
        if ($requester['role'] === 'student') {
            if ($requestedStudentName !== null && $requestedStudentName !== $requester['name']) {
                throw ApiException::forbidden('Você só pode consultar o próprio progresso.');
            }

            return Identity::applyOwnRows(StudentProgress::query(), $requester)->get()->map->toArray()->all();
        }

        if ($requester['role'] === 'instructor') {
            $courseIds = $this->instructorCourseIds($requester);

            return StudentProgress::query()
                ->whereIn('courseId', $courseIds)
                ->when($requestedStudentName !== null, fn ($q) => $q->where('studentName', $requestedStudentName))
                ->get()->map->toArray()->all();
        }

        return StudentProgress::query()
            ->when($requestedStudentName !== null, fn ($q) => $q->where('studentName', $requestedStudentName))
            ->get()->map->toArray()->all();
    }

    /**
     * @param  array{studentName:string,courseId:string,completedLessons:array,attendedLiveSessions:array}  $input
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<string, mixed>
     */
    public function upsertProgress(array $input, array $requester): array
    {
        if ($requester['role'] === 'student' && $input['studentName'] !== $requester['name']) {
            throw ApiException::forbidden('Você só pode atualizar o próprio progresso.');
        }
        if ($requester['role'] === 'instructor') {
            throw ApiException::forbidden('Instrutores não registram progresso em nome do aluno.');
        }

        $userId = Identity::resolveStudentUserId($input['studentName'], $requester);
        $enrollmentId = StudentEnrollment::query()->where('studentName', $input['studentName'])->value('id');

        $existing = StudentProgress::query()
            ->where('studentName', $input['studentName'])
            ->where('courseId', $input['courseId'])
            ->first();

        $data = [
            'studentName' => $input['studentName'],
            'courseId' => $input['courseId'],
            'completedLessons' => $input['completedLessons'],
            'attendedLiveSessions' => $input['attendedLiveSessions'],
            'userId' => $userId,
            'enrollmentId' => $enrollmentId,
        ];

        if ($existing !== null) {
            $existing->fill($data)->save();

            return $existing->toArray();
        }

        $data['id'] = (string) Str::uuid();
        $created = StudentProgress::query()->create($data);

        return $created->toArray();
    }

    // ---------- MATRÍCULA ----------

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<string, mixed>
     */
    public function getEnrollments(array $requester): array
    {
        if ($requester['role'] === 'student') {
            $row = Identity::applyOwnRows(StudentEnrollment::query(), $requester)->first();

            return [$requester['name'] => $row ? $this->toPublicEnrollment($row) : self::EMPTY_ENROLLMENT];
        }

        $map = [];
        foreach (StudentEnrollment::query()->get() as $row) {
            $map[$row->studentName] = $this->toPublicEnrollment($row);
        }

        return $map;
    }

    /**
     * @param  array{enrolledCourseId?:string|null,enrolledAt?:string|null,completedCourseIds?:array,dropOutPenaltyUntil?:string|null}  $updates
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<string, mixed>
     */
    public function upsertEnrollment(string $studentName, array $updates, array $requester): array
    {
        $this->assertInstructorCanManage($updates['enrolledCourseId'] ?? null, $requester);

        $current = StudentEnrollment::query()->where('studentName', $studentName)->first();
        $userId = $current->userId ?? Identity::resolveStudentUserId($studentName, $requester);
        $merged = array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), $updates);

        $saved = $this->persistEnrollment($studentName, $merged, $userId);

        return $this->toPublicEnrollment($saved);
    }

    // ---------- AÇÕES DO PRÓPRIO ALUNO ----------

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array{enrollment: array<string, mixed>}
     */
    public function selfEnroll(string $courseId, array $requester): array
    {
        if (! Course::query()->whereKey($courseId)->exists()) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        $current = $this->ownEnrollment($requester);

        if ($this->hasActivePenalty($current)) {
            throw ApiException::forbidden('Você está em período de restrição temporária de nova matrícula por cancelamento tardio. Aguarde o fim da restrição ou solicite liberação à coordenação.');
        }
        if ($current?->enrolledCourseId) {
            throw new ApiException(409, 'CONFLICT', 'Você já possui uma matrícula ativa. Conclua ou cancele o curso atual antes de iniciar outro.');
        }

        $merged = array_merge(self::EMPTY_ENROLLMENT, [
            'completedCourseIds' => $current->completedCourseIds ?? [],
            'dropOutPenaltyUntil' => $current?->dropOutPenaltyUntil,
            'enrolledCourseId' => $courseId,
            'enrolledAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
        $saved = $this->persistEnrollment($requester['name'], $merged, $requester['sub']);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array{enrollment: array<string, mixed>, penaltyApplied: bool}
     */
    public function selfDrop(string $courseId, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        if (! $current?->enrolledCourseId || $current->enrolledCourseId !== $courseId) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        // Dias contados a partir do enrolledAt REAL persistido — nunca de valor do cliente.
        $penaltyApplied = false;
        $penaltyUntil = $current->dropOutPenaltyUntil;
        if (config('features.penalidadesCancelamento') === true && $current->enrolledAt) {
            $enrolledAt = CarbonImmutable::parse($current->enrolledAt);
            $daysEnrolled = (int) ceil($enrolledAt->diffInSeconds(CarbonImmutable::now()) / 86400);
            if ($daysEnrolled > BusinessRules::dropoutPenaltyFreeDays()) {
                $penaltyApplied = true;
                $penaltyUntil = CarbonImmutable::now()->addDays(BusinessRules::dropoutPenaltyDays())->toIso8601String();
            }
        }

        $merged = array_merge($this->currentRest($current), [
            'enrolledCourseId' => null,
            'enrolledAt' => null,
            'dropOutPenaltyUntil' => $penaltyUntil,
        ]);
        $saved = $this->persistEnrollment($requester['name'], $merged, $current->userId ?? $requester['sub']);

        return ['enrollment' => $this->toPublicEnrollment($saved), 'penaltyApplied' => $penaltyApplied];
    }

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array{enrollment: array<string, mixed>}
     */
    public function selfComplete(string $courseId, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        if (! $current?->enrolledCourseId || $current->enrolledCourseId !== $courseId) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        $course = Course::query()->with(['lessons', 'liveSessions'])->find($courseId);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        $progress = StudentProgress::query()
            ->where('studentName', $requester['name'])->where('courseId', $courseId)->first();

        $totalActivities = $course->lessons->count() + $course->liveSessions->count();
        $done = count($progress->completedLessons ?? []) + count($progress->attendedLiveSessions ?? []);
        $attendance = $totalActivities === 0 ? 0 : min(100, (int) round(($done / $totalActivities) * 100));
        $minAttendance = BusinessRules::courseMinAttendance($course->minAttendance);
        if ($attendance < $minAttendance) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para concluir o curso ({$attendance}% de {$minAttendance}% exigidos).");
        }

        $completed = array_values(array_unique([...($current->completedCourseIds ?? []), $courseId]));
        $merged = array_merge($this->currentRest($current), [
            'enrolledCourseId' => null,
            'enrolledAt' => null,
            'completedCourseIds' => $completed,
        ]);
        $saved = $this->persistEnrollment($requester['name'], $merged, $current->userId ?? $requester['sub']);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    // ---------- ADMISSÕES ----------

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listAdmissions(array $requester): array
    {
        if ($requester['role'] === 'student') {
            return Identity::applyOwnRows(AdmissionRequest::query(), $requester)->get()->map->toArray()->all();
        }
        if ($requester['role'] === 'instructor') {
            $courseIds = $this->instructorCourseIds($requester);

            return AdmissionRequest::query()->whereIn('courseId', $courseIds)->get()->map->toArray()->all();
        }

        return AdmissionRequest::query()->get()->map->toArray()->all();
    }

    /**
     * @param  array{id?:string,studentName:string,courseId:string}  $input
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<string, mixed>
     */
    public function createAdmission(array $input, array $requester): array
    {
        if ($requester['role'] === 'student' && $input['studentName'] !== $requester['name']) {
            throw ApiException::forbidden('Você só pode solicitar matrícula em seu próprio nome.');
        }

        $duplicate = AdmissionRequest::query()
            ->where('studentName', $input['studentName'])
            ->where('courseId', $input['courseId'])
            ->where('status', 'pending')
            ->exists();
        if ($duplicate) {
            throw new ApiException(409, 'CONFLICT', 'Matrícula pendente para este curso já registrada.');
        }

        $userId = Identity::resolveStudentUserId($input['studentName'], $requester);

        $admission = AdmissionRequest::query()->create([
            'id' => $input['id'] ?? ('adm-'.$this->nowMs()),
            'studentName' => $input['studentName'],
            'userId' => $userId,
            'courseId' => $input['courseId'],
            'status' => 'pending',
            'submittedAt' => CarbonImmutable::now()->format('d/m/Y'),
        ]);

        return $admission->toArray();
    }

    /**
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array<string, mixed>
     */
    public function updateAdmissionStatus(string $id, string $status, array $requester): array
    {
        $admission = AdmissionRequest::query()->find($id);
        if ($admission === null) {
            throw ApiException::notFound('Matrícula não encontrada.');
        }

        $this->assertInstructorCanManage($admission->courseId, $requester);

        $studentUserId = $admission->userId ?? Identity::resolveStudentUserId($admission->studentName, $requester);

        // Aprovação efetiva a matrícula na MESMA transação — nunca "aprovada" sem matricular.
        $updated = DB::transaction(function () use ($admission, $status, $studentUserId) {
            $admission->status = $status;
            $admission->save();

            if ($status === 'approved') {
                $current = StudentEnrollment::query()->where('studentName', $admission->studentName)->first();
                $merged = array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), [
                    'enrolledCourseId' => $admission->courseId,
                    'enrolledAt' => CarbonImmutable::now()->toIso8601String(),
                    'dropOutPenaltyUntil' => null,
                ]);
                $this->persistEnrollment($admission->studentName, $merged, $studentUserId);
            }

            return $admission;
        });

        return $updated->toArray();
    }

    // ---------- helpers ----------

    /** @param array{sub:string,name:mixed,role:mixed} $requester */
    private function assertInstructorCanManage(?string $courseId, array $requester): void
    {
        if ($requester['role'] === 'admin') {
            return;
        }
        if ($requester['role'] === 'instructor' && $courseId) {
            $course = Course::query()->find($courseId, ['instructorName', 'instructorId']);
            if ($course !== null) {
                $owns = $course->instructorId
                    ? $course->instructorId === $requester['sub']
                    : $course->instructorName === $requester['name'];
                if ($owns) {
                    return;
                }
            }
        }
        throw ApiException::forbidden('Você só pode gerenciar matrículas de cursos vinculados ao seu perfil.');
    }

    /** @param array{sub:string,name:mixed,role:mixed} $requester */
    private function ownEnrollment(array $requester): ?StudentEnrollment
    {
        return Identity::applyOwnRows(StudentEnrollment::query(), $requester)->first();
    }

    private function hasActivePenalty(?StudentEnrollment $row): bool
    {
        if (config('features.penalidadesCancelamento') !== true) {
            return false;
        }
        if (! $row?->dropOutPenaltyUntil) {
            return false;
        }
        try {
            return CarbonImmutable::parse($row->dropOutPenaltyUntil)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $merged
     */
    private function persistEnrollment(string $studentName, array $merged, ?string $userId): StudentEnrollment
    {
        $existing = StudentEnrollment::query()->where('studentName', $studentName)->first();
        if ($existing !== null) {
            $existing->fill(array_merge($merged, ['userId' => $userId]))->save();

            return $existing;
        }

        return StudentEnrollment::query()->create(array_merge($merged, [
            'studentName' => $studentName,
            'id' => (string) Str::uuid(),
            'userId' => $userId,
        ]));
    }

    /**
     * Campos da matrícula atual sem studentName/id/userId (para o merge de atualização).
     *
     * @return array<string, mixed>
     */
    private function currentRest(?StudentEnrollment $current): array
    {
        if ($current === null) {
            return [];
        }
        $rest = $current->only(['enrolledCourseId', 'enrolledAt', 'completedCourseIds', 'dropOutPenaltyUntil']);

        return $rest;
    }

    /**
     * Forma pública da matrícula: sem studentName/id/userId (igual ao toPublicEnrollment do Node).
     *
     * @return array<string, mixed>
     */
    private function toPublicEnrollment(StudentEnrollment $row): array
    {
        return [
            'enrolledCourseId' => $row->enrolledCourseId,
            'enrolledAt' => $row->enrolledAt,
            'completedCourseIds' => $row->completedCourseIds ?? [],
            'dropOutPenaltyUntil' => $row->dropOutPenaltyUntil,
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
