<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\GestoresDoCurso;
use Illuminate\Support\Facades\DB;

/**
 * Escopo de um instrutor por FK (ADR 10): quais cursos ele leciona e quais alunos
 * estão sob sua responsabilidade. Fonte única desta regra — antes duplicada em
 * AuthService::listStudentsForInstructor e EnrollmentService::instructorCourseIds.
 * Um instrutor nunca enxerga dados de cursos/alunos fora deste escopo; admin é irrestrito.
 *
 * $requester = ['sub'=>id, 'nome'=>..., 'papel'=>...].
 */
final class InstructorScope
{
    /**
     * IDs dos cursos lecionados pelo instrutor (responsável em `curso.instrutor_id` ou gestor adicional em `curso_gestor`).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return list<string>
     */
    public static function courseIds(array $requester): array
    {
        return (new GestoresDoCurso)->cursoIdsDe($requester['sub']);
    }

    /**
     * IDs dos alunos sob responsabilidade do instrutor: admitidos (admissão aprovada)
     * ou matriculados em algum de seus cursos. Vínculo por FK usuario_id apenas.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return list<string>
     */
    public static function studentIds(array $requester): array
    {
        $courseIds = self::courseIds($requester);
        if (count($courseIds) === 0) {
            return [];
        }

        $admissions = DB::table('solicitacao_ingresso')
            ->whereIn('curso_id', $courseIds)
            ->where('status', 'approved')
            ->pluck('usuario_id');

        /*
         * A matrícula CONCORRENTE conta. `curso_matriculado_id` é só o curso
         * principal; quando a coordenação concede matrícula múltipla, os
         * outros cursos ficam em `cursos_extras_ids`, e o aluno é tão aluno
         * daquele curso quanto o do principal.
         *
         * Sem esta metade, o instrutor não enxergava esses alunos em NADA que
         * depende deste escopo — mensagens diretas, matrículas, lista de
         * alunos. Apareceu com a caixa de mensagens em Blade (21/10): havia
         * dúvida de aluno sem gestor que a alcançasse, porque o curso dele era
         * concorrente. É alargamento de escopo de autorização, e está
         * sinalizado no plano 18 §23 para validação humana.
         *
         * `cursos_concluidos_ids` NÃO entra: quem concluiu deixou de ser aluno em
         * curso, e incluí-lo manteria todo ex-aluno no escopo para sempre.
         */
        $enrollments = DB::table('matricula')
            ->where(static function ($q) use ($courseIds): void {
                $q->whereIn('curso_matriculado_id', $courseIds);
                foreach ($courseIds as $curso_id) {
                    // Coluna JSON com lista de ids; `LIKE` com as aspas evita
                    // casar um id que seja prefixo de outro.
                    $q->orWhere('cursos_extras_ids', 'like', '%"'.$curso_id.'"%');
                }
            })
            ->pluck('usuario_id');

        return array_values($admissions->concat($enrollments)
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->unique()
            ->all());
    }
}
