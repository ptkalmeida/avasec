<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AdmissionRequest;
use App\Models\StudentEnrollment;

/**
 * Quais cursos um requester pode acessar como MEMBRO (para leitura de conteúdo de
 * turma, ex.: chat da aula ao vivo). Vínculo por FK apenas (ADR 10):
 *  - admin: irrestrito;
 *  - instrutor: cursos que leciona (InstructorScope);
 *  - aluno: curso em que está matriculado, que já concluiu, ou com admissão aprovada.
 *
 * $requester = ['sub'=>id, 'name'=>..., 'role'=>...].
 */
final class CourseAccess
{
    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function canAccess(array $requester, string $courseId): bool
    {
        if ($requester['role'] === 'admin') {
            return true;
        }
        if ($requester['role'] === 'instructor') {
            return in_array($courseId, InstructorScope::courseIds($requester), true);
        }

        return in_array($courseId, self::studentCourseIds($requester['sub']), true);
    }

    /**
     * IDs de cursos acessíveis ao requester NÃO-admin (instrutor: leciona; aluno:
     * pertence). O chamador trata admin como irrestrito antes de invocar.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return list<string>
     */
    public static function accessibleCourseIds(array $requester): array
    {
        if ($requester['role'] === 'instructor') {
            return InstructorScope::courseIds($requester);
        }

        return self::studentCourseIds($requester['sub']);
    }

    /**
     * IDs dos cursos aos quais um aluno pertence (matrícula ativa + concluídos +
     * admissões aprovadas).
     *
     * @return list<string>
     */
    private static function studentCourseIds(string $userId): array
    {
        $ids = [];

        $enrollment = StudentEnrollment::query()->where('userId', $userId)->first();
        if ($enrollment !== null) {
            if (is_string($enrollment->enrolledCourseId) && $enrollment->enrolledCourseId !== '') {
                $ids[] = $enrollment->enrolledCourseId;
            }
            foreach ((array) ($enrollment->completedCourseIds ?? []) as $courseId) {
                if (is_string($courseId) && $courseId !== '') {
                    $ids[] = $courseId;
                }
            }
        }

        $approved = AdmissionRequest::query()
            ->where('userId', $userId)
            ->where('status', 'approved')
            ->pluck('courseId')
            ->all();
        foreach ($approved as $courseId) {
            if (is_string($courseId) && $courseId !== '') {
                $ids[] = $courseId;
            }
        }

        return array_values(array_unique($ids));
    }
}
