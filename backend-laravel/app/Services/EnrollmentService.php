<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AdmissionRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Support\BusinessRules;
use App\Support\Identity;
use App\Support\InstructorScope;
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
        'canMultiEnroll' => false,
        'extraCourseIds' => [],
    ];

    // ---------- PROGRESSO ----------

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function getProgress(?string $requestedUserId, array $requester): array
    {
        if ($requester['role'] === 'student') {
            if ($requestedUserId !== null && $requestedUserId !== $requester['sub']) {
                throw ApiException::forbidden('Você só pode consultar o próprio progresso.');
            }

            return Identity::applyOwnRows(StudentProgress::query(), $requester)->get()->map->toArray()->all();
        }

        if ($requester['role'] === 'instructor') {
            $courseIds = InstructorScope::courseIds($requester);

            return StudentProgress::query()
                ->whereIn('courseId', $courseIds)
                ->when($requestedUserId !== null, fn ($q) => $q->where('userId', $requestedUserId))
                ->get()->map->toArray()->all();
        }

        return StudentProgress::query()
            ->when($requestedUserId !== null, fn ($q) => $q->where('userId', $requestedUserId))
            ->get()->map->toArray()->all();
    }

    /**
     * @param  array{userId?:string|null,courseId:string,completedLessons:list<string>,attendedLiveSessions:list<string>}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function upsertProgress(array $input, array $requester): array
    {
        if ($requester['role'] === 'instructor') {
            throw ApiException::forbidden('Instrutores não registram progresso em nome do aluno.');
        }

        $userId = Identity::resolveActorUserId($requester, $input['userId'] ?? null);
        $enrollmentId = StudentEnrollment::query()->whereKey($userId)->value('id');

        // Integridade acadêmica: só contam como progresso os IDs de aulas/sessões que
        // REALMENTE pertencem ao curso, sem duplicatas. Sem isto, o aluno inflava a
        // própria frequência (e forjava certificado) enviando IDs inventados/repetidos,
        // porque a frequência é recalculada a partir da contagem deste array.
        [$completedLessons, $attendedLiveSessions] = $this->sanitizeProgressIds(
            $input['courseId'],
            $input['completedLessons'],
            $input['attendedLiveSessions'],
        );

        $existing = StudentProgress::query()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->first();

        $data = [
            'studentName' => Identity::displayName($userId, $requester),
            'courseId' => $input['courseId'],
            'completedLessons' => $completedLessons,
            'attendedLiveSessions' => $attendedLiveSessions,
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

    /**
     * Filtra as listas de progresso do cliente para conter apenas IDs de aulas
     * (Lesson) e sessões ao vivo (LiveSession) que pertencem ao curso, sem
     * duplicatas. Comparação por FK apenas (ADR 10).
     *
     * @param  list<string>  $completedLessons
     * @param  list<string>  $attendedLiveSessions
     * @return array{0: list<string>, 1: list<string>}
     */
    private function sanitizeProgressIds(string $courseId, array $completedLessons, array $attendedLiveSessions): array
    {
        $validLessonIds = array_values(array_filter(
            Lesson::query()->where('courseId', $courseId)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));
        $validSessionIds = array_values(array_filter(
            LiveSession::query()->where('courseId', $courseId)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));

        $keepLessons = array_values(array_unique(array_intersect($completedLessons, $validLessonIds)));
        $keepSessions = array_values(array_unique(array_intersect($attendedLiveSessions, $validSessionIds)));

        return [$keepLessons, $keepSessions];
    }

    // ---------- MATRÍCULA ----------

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function getEnrollments(array $requester): array
    {
        if ($requester['role'] === 'student') {
            $row = Identity::applyOwnRows(StudentEnrollment::query(), $requester)->first();

            return [
                $requester['sub'] => $row
                    ? $this->toPublicEnrollment($row)
                    : array_merge(self::EMPTY_ENROLLMENT, ['userId' => $requester['sub'], 'studentName' => $requester['name']]),
            ];
        }

        // Instrutor só vê matrículas dos próprios alunos (admitidos/matriculados em seus
        // cursos); admin vê todas. Antes vazava o mapa completo para qualquer não-aluno.
        $query = StudentEnrollment::query();
        if ($requester['role'] === 'instructor') {
            $query->whereIn('userId', InstructorScope::studentIds($requester));
        }

        $map = [];
        foreach ($query->get() as $row) {
            $map[$row->userId] = $this->toPublicEnrollment($row);
        }

        return $map;
    }

    /**
     * @param  array{enrolledCourseId?:string|null,enrolledAt?:string|null,completedCourseIds?:list<string>,dropOutPenaltyUntil?:string|null,canMultiEnroll?:bool}  $updates
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function upsertEnrollment(string $userId, array $updates, array $requester): array
    {
        $this->assertInstructorCanManage($updates['enrolledCourseId'] ?? null, $requester);

        // Só o Admin Superior concede matrícula múltipla concorrente — nem o
        // instrutor dono do curso pode ligar isso para os próprios alunos.
        if (array_key_exists('canMultiEnroll', $updates) && $requester['role'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode conceder matrícula múltipla simultânea.');
        }

        $targetUserId = Identity::resolveActorUserId($requester, $userId);
        $current = StudentEnrollment::query()->whereKey($targetUserId)->first();
        $merged = array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), $updates);

        $saved = $this->persistEnrollment($targetUserId, Identity::displayName($targetUserId, $requester), $merged);

        return $this->toPublicEnrollment($saved);
    }

    // ---------- AÇÕES DO PRÓPRIO ALUNO ----------

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
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

        $extraCourseIds = $current->extraCourseIds ?? [];
        if ($current?->enrolledCourseId === $courseId || in_array($courseId, $extraCourseIds, true)) {
            throw new ApiException(409, 'CONFLICT', 'Você já está matriculado neste curso.');
        }

        if ($current?->enrolledCourseId) {
            // Segunda (ou mais) matrícula simultânea: só permitido com a flag global
            // ligada E a permissão concedida pelo Admin Superior a este aluno.
            if (config('features.matriculasMultiplas') !== true || $current->canMultiEnroll !== true) {
                throw new ApiException(409, 'CONFLICT', 'Você já possui uma matrícula ativa. Conclua ou cancele o curso atual antes de iniciar outro.');
            }

            $merged = array_merge($this->currentRest($current), [
                'extraCourseIds' => array_values(array_unique([...$extraCourseIds, $courseId])),
            ]);
            $saved = $this->persistEnrollment($requester['sub'], $requester['name'], $merged);

            return ['enrollment' => $this->toPublicEnrollment($saved)];
        }

        $merged = array_merge(self::EMPTY_ENROLLMENT, [
            'completedCourseIds' => $current->completedCourseIds ?? [],
            'dropOutPenaltyUntil' => $current?->dropOutPenaltyUntil,
            'canMultiEnroll' => $current->canMultiEnroll ?? false,
            'extraCourseIds' => $extraCourseIds,
            'enrolledCourseId' => $courseId,
            'enrolledAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
        $saved = $this->persistEnrollment($requester['sub'], $requester['name'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array{enrollment: array<string, mixed>, penaltyApplied: bool}
     */
    public function selfDrop(string $courseId, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        $extraCourseIds = $current->extraCourseIds ?? [];
        $isPrimary = $current?->enrolledCourseId === $courseId;
        $isExtra = in_array($courseId, $extraCourseIds, true);
        if (! $isPrimary && ! $isExtra) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        // Cancelamento de uma matrícula EXTRA (concorrente): sem penalidade — a
        // penalidade de cancelamento tardio só se aplica ao curso principal, único
        // com `enrolledAt` rastreado.
        if ($isExtra) {
            $merged = array_merge($this->currentRest($current), [
                'extraCourseIds' => array_values(array_filter($extraCourseIds, fn ($id) => $id !== $courseId)),
            ]);
            $saved = $this->persistEnrollment($requester['sub'], $requester['name'], $merged);

            return ['enrollment' => $this->toPublicEnrollment($saved), 'penaltyApplied' => false];
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
        $saved = $this->persistEnrollment($requester['sub'], $requester['name'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved), 'penaltyApplied' => $penaltyApplied];
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array{enrollment: array<string, mixed>}
     */
    public function selfComplete(string $courseId, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        $extraCourseIds = $current->extraCourseIds ?? [];
        $isPrimary = $current?->enrolledCourseId === $courseId;
        $isExtra = in_array($courseId, $extraCourseIds, true);
        if (! $isPrimary && ! $isExtra) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        $course = Course::query()->with(['lessons', 'liveSessions'])->find($courseId);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        $progress = StudentProgress::query()
            ->where('userId', $requester['sub'])->where('courseId', $courseId)->first();

        $totalActivities = $course->lessons->count() + $course->liveSessions->count();
        $done = count($progress->completedLessons ?? []) + count($progress->attendedLiveSessions ?? []);
        $attendance = $totalActivities === 0 ? 0 : min(100, (int) round(($done / $totalActivities) * 100));
        $minAttendance = BusinessRules::courseMinAttendance($course->minAttendance);
        if ($attendance < $minAttendance) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para concluir o curso ({$attendance}% de {$minAttendance}% exigidos).");
        }

        $completed = array_values(array_unique([...($current->completedCourseIds ?? []), $courseId]));
        $merged = array_merge($this->currentRest($current), [
            'completedCourseIds' => $completed,
            ...($isExtra
                ? ['extraCourseIds' => array_values(array_filter($extraCourseIds, fn ($id) => $id !== $courseId))]
                : ['enrolledCourseId' => null, 'enrolledAt' => null]),
        ]);
        $saved = $this->persistEnrollment($requester['sub'], $requester['name'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    // ---------- ADMISSÕES ----------

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listAdmissions(array $requester): array
    {
        if ($requester['role'] === 'student') {
            return Identity::applyOwnRows(AdmissionRequest::query(), $requester)->get()->map->toArray()->all();
        }
        if ($requester['role'] === 'instructor') {
            $courseIds = InstructorScope::courseIds($requester);

            return AdmissionRequest::query()->whereIn('courseId', $courseIds)->get()->map->toArray()->all();
        }

        return AdmissionRequest::query()->get()->map->toArray()->all();
    }

    /**
     * @param  array{id?:string|null,userId?:string|null,courseId:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createAdmission(array $input, array $requester): array
    {
        $userId = Identity::resolveActorUserId($requester, $input['userId'] ?? null);

        $duplicate = AdmissionRequest::query()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->where('status', 'pending')
            ->exists();
        if ($duplicate) {
            throw new ApiException(409, 'CONFLICT', 'Matrícula pendente para este curso já registrada.');
        }

        $admission = AdmissionRequest::query()->create([
            'id' => $input['id'] ?? ('adm-'.$this->nowMs()),
            'studentName' => Identity::displayName($userId, $requester),
            'userId' => $userId,
            'courseId' => $input['courseId'],
            'status' => 'pending',
            'submittedAt' => CarbonImmutable::now()->format('d/m/Y'),
        ]);

        return $admission->toArray();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function updateAdmissionStatus(string $id, string $status, array $requester): array
    {
        $admission = AdmissionRequest::query()->find($id);
        if ($admission === null) {
            throw ApiException::notFound('Matrícula não encontrada.');
        }

        $this->assertInstructorCanManage($admission->courseId, $requester);

        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw ApiException::validation('Status de matrícula inválido.');
        }

        $studentUserId = $admission->userId;

        // Aprovação efetiva a matrícula na MESMA transação — nunca "aprovada" sem matricular.
        $updated = DB::transaction(function () use ($admission, $status, $studentUserId) {
            $admission->status = $status;
            $admission->save();

            if ($status === 'approved') {
                $current = StudentEnrollment::query()->whereKey($studentUserId)->first();
                $merged = array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), [
                    'enrolledCourseId' => $admission->courseId,
                    'enrolledAt' => CarbonImmutable::now()->toIso8601String(),
                    'dropOutPenaltyUntil' => null,
                ]);
                $this->persistEnrollment($studentUserId, $admission->studentName, $merged);
            }

            return $admission;
        });

        return $updated->toArray();
    }

    // ---------- helpers ----------

    /** @param array{sub:string,name:string,role:string} $requester */
    private function assertInstructorCanManage(?string $courseId, array $requester): void
    {
        if ($requester['role'] === 'admin') {
            return;
        }
        if ($requester['role'] === 'instructor' && $courseId) {
            $course = Course::query()->find($courseId, ['instructorId']);
            // Posse por FK apenas (ADR 10).
            if ($course !== null && $course->instructorId !== null && $course->instructorId === $requester['sub']) {
                return;
            }
        }
        throw ApiException::forbidden('Você só pode gerenciar matrículas de cursos vinculados ao seu perfil.');
    }

    /** @param array{sub:string,name:string,role:string} $requester */
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
     * Upsert keyed pela PK userId (ADR 10); studentName é apenas snapshot de exibição.
     *
     * @param  array<string, mixed>  $merged
     */
    private function persistEnrollment(string $userId, string $studentName, array $merged): StudentEnrollment
    {
        $existing = StudentEnrollment::query()->whereKey($userId)->first();
        if ($existing !== null) {
            $existing->fill(array_merge($merged, ['studentName' => $studentName]))->save();

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
        $rest = $current->only([
            'enrolledCourseId', 'enrolledAt', 'completedCourseIds', 'dropOutPenaltyUntil',
            'canMultiEnroll', 'extraCourseIds',
        ]);

        return $rest;
    }

    /**
     * Forma pública da matrícula. Desde a ADR 10 inclui userId (identidade) e
     * studentName (display) para o frontend indexar por id.
     *
     * @return array<string, mixed>
     */
    private function toPublicEnrollment(StudentEnrollment $row): array
    {
        return [
            'userId' => $row->userId,
            'studentName' => $row->studentName,
            'enrolledCourseId' => $row->enrolledCourseId,
            'enrolledAt' => $row->enrolledAt,
            'completedCourseIds' => $row->completedCourseIds ?? [],
            'dropOutPenaltyUntil' => $row->dropOutPenaltyUntil,
            'canMultiEnroll' => (bool) $row->canMultiEnroll,
            'extraCourseIds' => $row->extraCourseIds ?? [],
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
