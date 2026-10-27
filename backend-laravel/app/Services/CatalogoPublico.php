<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Support\Visibilidade;
use App\Support\VigenciaDoCurso;

/**
 * Vitrine do portal público: os cursos que alguém pode cursar agora.
 *
 * Substitui `config/catalogo_publico.php`, uma réplica do array fixo do React
 * que mostrava cursos inexistentes na plataforma. "Cursável" é o curso ativo
 * neste momento, e são três condições juntas:
 *
 * - **publicado** (`status` 1 na escada da ADR 12) — rascunho e restrito não
 *   aparecem ao público;
 * - **não inativado** — o `SoftDeletes` de `curso` já o exclui da consulta;
 * - **vigência não vencida** — o mesmo critério de `VigenciaDoCurso` que barra a
 *   matrícula. Curso sem prazo é de vigência permanente e entra.
 *
 * A ordem é do curso mais novo para o mais antigo. `curso` não tem coluna de
 * data de criação, e o único registro de quando o curso nasceu é o próprio id:
 * o painel o gera como `course-<milissegundos>` (`CourseService::createCourse`),
 * e os cursos de seed (`course-1`, `course-2`...) têm número pequeno e ficam por
 * último. Um id sem número no fim vale 0. Se o schema ganhar uma data de
 * criação ou de publicação, é ela que deve substituir esta ordenação.
 */
final class CatalogoPublico
{
    /**
     * @return list<array{titulo:string,categoria:string,instructor:string,imagem:string,descricao:string}>
     */
    public function cursaveis(): array
    {
        $consulta = Curso::query()->select([
            'id', 'titulo', 'categoria', 'nome_instrutor', 'miniatura', 'imagem_capa',
            'descricao', 'vigencia_ate', 'vencimento_contrato_texto',
        ]);
        Visibilidade::aplicar($consulta, null);

        $vitrine = [];

        $cursos = $consulta->get()->sortByDesc(
            static fn (Curso $c): int => preg_match('/(\d+)$/', (string) $c->id, $m) === 1 ? (int) $m[1] : 0,
        );

        foreach ($cursos as $curso) {
            $vigencia = [
                'vigencia_ate' => $curso->getAttribute('vigencia_ate'),
                'vencimento_contrato_texto' => $curso->vencimento_contrato_texto,
            ];

            if (VigenciaDoCurso::vencida($vigencia)) {
                continue;
            }

            $vitrine[] = [
                'titulo' => (string) $curso->titulo,
                'categoria' => (string) $curso->categoria,
                'instructor' => trim((string) $curso->nome_instrutor),
                'imagem' => (string) ($curso->miniatura ?: $curso->imagem_capa),
                'descricao' => trim((string) $curso->descricao),
            ];
        }

        return $vitrine;
    }
}
