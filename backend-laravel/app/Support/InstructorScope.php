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

        /*
         * A matrícula CONCORRENTE conta. `enrolledCourseId` é só o curso
         * principal; quando a coordenação concede matrícula múltipla, os
         * outros cursos ficam em `extraCourseIds`, e o aluno é tão aluno
         * daquele curso quanto o do principal.
         *
         * Sem esta metade, o instrutor não enxergava esses alunos em NADA que
         * depende deste escopo — mensagens diretas, matrículas, lista de
         * alunos. Apareceu com a caixa de mensagens em Blade (21/10): havia
         * dúvida de aluno sem gestor que a alcançasse, porque o curso dele era
         * concorrente. É alargamento de escopo de autorização, e está
         * sinalizado no plano 18 §23 para validação humana.
         *
         * `completedCourseIds` NÃO entra: quem concluiu deixou de ser aluno em
         * curso, e incluí-lo manteria todo ex-aluno no escopo para sempre.
         */
        $enrollments = DB::table('StudentEnrollment')
            ->where(static function ($q) use ($courseIds): void {
                $q->whereIn('enrolledCourseId', $courseIds);
                foreach ($courseIds as $courseId) {
                    // Coluna JSON com lista de ids; `LIKE` com as aspas evita
                    // casar um id que seja prefixo de outro.
                    $q->orWhere('extraCourseIds', 'like', '%"'.$courseId.'"%');
                }
            })
            ->pluck('userId');

        return array_values($admissions->concat($enrollments)
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->unique()
            ->all());
    }
}
