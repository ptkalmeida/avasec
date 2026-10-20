<?php

declare(strict_types=1);

namespace App\Services;

/**
 * O que o menu lateral do aluno precisa saber, calculado num lugar só.
 *
 * Existe por uma armadilha que já ia acontecer. O submenu "Cursos concluídos"
 * recebia a lista de cada PÁGINA, e o layout usava `[]` quando a página não a
 * passava. Toda tela nova que esquecesse faria o menu afirmar "Nenhum curso
 * concluído ainda" para quem concluiu — sem erro, sem aviso, só uma frase
 * falsa. Com este serviço, quem esquece recebe a lista certa.
 *
 * Usa os MESMOS serviços que a área do aluno: a matrícula vem do
 * `EnrollmentService` e os cursos de `listCoursesFor`, que aplica a escada de
 * visibilidade — curso inativado ou em rascunho não aparece no menu.
 */
final class MenuDoAluno
{
    public function __construct(
        private readonly EnrollmentService $matriculas,
        private readonly CourseService $cursos,
    ) {}

    /**
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return list<array{id:string,slug:string|null,title:string}>
     */
    public function concluidos(array $aluno): array
    {
        $linha = $this->matriculas->getEnrollments($aluno)[$aluno['sub']] ?? [];
        $ids = is_array($linha) && is_array($linha['completedCourseIds'] ?? null) ? $linha['completedCourseIds'] : [];

        if ($ids === []) {
            return [];
        }

        $porId = [];
        foreach ($this->cursos->listCoursesFor($aluno) as $curso) {
            if (is_string($curso['id'] ?? null)) {
                $porId[$curso['id']] = $curso;
            }
        }

        $lista = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! isset($porId[$id])) {
                continue;
            }

            $curso = $porId[$id];
            $lista[] = [
                'id' => $id,
                'slug' => is_string($curso['slug'] ?? null) && $curso['slug'] !== '' ? $curso['slug'] : null,
                'title' => is_string($curso['title'] ?? null) ? $curso['title'] : '',
            ];
        }

        return $lista;
    }
}
