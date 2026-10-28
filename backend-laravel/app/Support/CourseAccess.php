<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Matricula;
use App\Models\SolicitacaoIngresso;

/**
 * Quais cursos um requester pode acessar como MEMBRO (para leitura de conteúdo de
 * turma, ex.: chat da aula ao vivo). Vínculo por FK apenas (ADR 10):
 *  - admin: irrestrito;
 *  - instrutor: cursos que leciona (InstructorScope);
 *  - aluno: curso em que está matriculado (principal ou extra, na matrícula
 *    múltipla), que já concluiu, ou com admissão aprovada.
 *
 * $requester = ['sub'=>id, 'nome'=>..., 'papel'=>...].
 */
final class CourseAccess
{
    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public static function canAccess(array $requester, string $curso_id): bool
    {
        if ($requester['papel'] === 'admin') {
            return true;
        }
        if ($requester['papel'] === 'instructor') {
            return in_array($curso_id, InstructorScope::courseIds($requester), true);
        }

        return in_array($curso_id, self::studentCourseIds($requester['sub']), true);
    }

    /**
     * IDs de cursos acessíveis ao requester NÃO-admin (instrutor: leciona; aluno:
     * pertence). O chamador trata admin como irrestrito antes de invocar.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return list<string>
     */
    public static function accessibleCourseIds(array $requester): array
    {
        if ($requester['papel'] === 'instructor') {
            return InstructorScope::courseIds($requester);
        }

        return self::studentCourseIds($requester['sub']);
    }

    /**
     * IDs dos cursos aos quais um aluno pertence (matrícula ativa, principal e
     * extras + concluídos + admissões aprovadas).
     *
     * @return list<string>
     */
    private static function studentCourseIds(string $usuario_id): array
    {
        $ids = [];

        $enrollment = Matricula::query()->where('usuario_id', $usuario_id)->first();
        if ($enrollment !== null) {
            if (is_string($enrollment->curso_matriculado_id) && $enrollment->curso_matriculado_id !== '') {
                $ids[] = $enrollment->curso_matriculado_id;
            }
            foreach ((array) ($enrollment->cursos_concluidos_ids ?? []) as $curso_id) {
                if (is_string($curso_id) && $curso_id !== '') {
                    $ids[] = $curso_id;
                }
            }
            // Matrícula múltipla: o EnrollmentService grava o segundo curso aqui e o
            // painel do aluno o trata como matrícula. Sem esta leitura, o aluno abria
            // o curso extra e recebia a versão sem material (semMaterial) — aula em
            // branco, sem anexos — e ficava barrado em avaliação, fórum e chat.
            foreach ((array) ($enrollment->cursos_extras_ids ?? []) as $curso_id) {
                if (is_string($curso_id) && $curso_id !== '') {
                    $ids[] = $curso_id;
                }
            }
        }

        $approved = SolicitacaoIngresso::query()
            ->where('usuario_id', $usuario_id)
            ->where('status', 'approved')
            ->pluck('curso_id')
            ->all();
        foreach ($approved as $curso_id) {
            if (is_string($curso_id) && $curso_id !== '') {
                $ids[] = $curso_id;
            }
        }

        return array_values(array_unique($ids));
    }
}
