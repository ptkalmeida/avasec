<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contas de progresso de um aluno num curso.
 *
 * Traduz `src/utils/courseProgress.ts`, e existe pelo mesmo defeito real que
 * motivou aquele arquivo: `StudentProgress.completedLessons` guarda ids de
 * aula, e quando uma aula é APAGADA do curso o id continua lá. A conta ingênua
 * `count(concluidas) / count(aulas)` passava a dividir 2 por 1, e o Perfil
 * exibia "113% de progresso médio".
 *
 * Regra: só conta o que AINDA existe no curso. Id órfão não é progresso, é
 * resíduo.
 *
 * ## Por que não reaproveitei o cálculo do CertificateService
 *
 * Porque é outra conta. Lá o número é **frequência** — aulas concluídas mais
 * presenças em encontros ao vivo sobre o total de atividades — e é ela que
 * decide a emissão do certificado. Aqui o número é **progresso nas aulas**, que
 * é o que a tela mostra. Unificar os dois faria um número de tela e um critério
 * de certificação compartilharem implementação, e a primeira mudança de layout
 * mexeria no que emite certificado.
 *
 * Funções puras, sobre listas de ids: assim se testa sem banco, e é onde o erro
 * de verdade mora.
 */
final class ProgressoDoAluno
{
    /**
     * Quantas das aulas marcadas como concluídas ainda existem no curso.
     *
     * @param  list<mixed>  $concluidas  ids vindos de `completedLessons`
     * @param  list<mixed>  $aulasDoCurso  ids das aulas que o curso tem hoje
     */
    public static function contarValidas(array $concluidas, array $aulasDoCurso): int
    {
        $somenteTexto = static fn (array $ids): array => array_values(
            array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== '')
        );

        return count(array_intersect(
            array_unique($somenteTexto($concluidas)),
            $somenteTexto($aulasDoCurso),
        ));
    }

    /**
     * Percentual de 0 a 100, para a barra. Curso sem aula nenhuma é 0, e não
     * 100: "concluiu tudo" seria uma afirmação sobre um curso vazio.
     *
     * @param  list<mixed>  $concluidas
     * @param  list<mixed>  $aulasDoCurso
     */
    public static function percentual(array $concluidas, array $aulasDoCurso): int
    {
        $total = count($aulasDoCurso);

        if ($total === 0) {
            return 0;
        }

        return min(100, (int) round((self::contarValidas($concluidas, $aulasDoCurso) / $total) * 100));
    }
}
