<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * Escopo de um instrutor por FK (ADR 10): quais cursos ele leciona e quais alunos
 * estão sob sua responsabilidade. Fonte única desta regra — antes duplicada em
 * AuthService::listStudentsForInstructor e EnrollmentService::instructorCourseIds.
 * Um instrutor nunca enxerga dados de cursos/alunos fora deste escopo; admin é irrestrito.
 *
 * $requester = ['sub'=>id, 'name'=>..., 'role'=>...].
 */
final class InstructorScope
{
    /**
     * IDs dos cursos lecionados pelo instrutor (posse por instructorId).
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return list<string>
     */
    public static function courseIds(array $requester): array
    {
        return array_values(array_filter(
            Course::query()->where('instructorId', $requester['sub'])->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));
    }

    /**
     * IDs dos alunos sob responsabilidade do instrutor: admitidos (admissão aprovada)
     * ou matriculados em algum de seus cursos. Vínculo por FK userId apenas.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return list<string>
     */
    public static function studentIds(array $requester): array
    {
        $courseIds = self::courseIds($requester);
        if (count($courseIds) === 0) {
            return [];
        }

        $admissions = DB::table('AdmissionRequest')
            ->whereIn('courseId', $courseIds)
            ->where('status', 'approved')
            ->pluck('userId');
        $enrollments = DB::table('StudentEnrollment')
            ->whereIn('enrolledCourseId', $courseIds)
            ->pluck('userId');

        return array_values($admissions->concat($enrollments)
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->unique()
            ->all());
    }
}
