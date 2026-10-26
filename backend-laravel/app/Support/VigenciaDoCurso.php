<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * A vigência de exibição de um curso acabou?
 *
 * `Course` guarda a data em duas colunas: `contractExpirationDate` (texto, o
 * que a tela mostra) e `vigenciaAte` (data nativa, Norma C.2.4). Esta classe
 * responde por ambas, e é a ÚNICA resposta — a pergunta aparece no catálogo do
 * aluno, na matrícula e no painel do instrutor, e três respostas parecidas
 * acabariam discordando.
 *
 * ## Por que isto importa mais que uma etiqueta
 *
 * Vigência vencida é licença de exibição expirada. O próprio painel do
 * instrutor descreve o curso vencido como "arquivado preventivamente ...
 * bloqueado no catálogo de alunos para proteção jurídica contra distribuição
 * ilegal de direitos autorais expirados".
 *
 * Esse bloqueio só existia no React (`StudentDashboard` filtrava a lista no
 * navegador). O servidor nunca filtrou: `listCoursesFor` devolve o curso
 * vencido como qualquer outro. Quando o catálogo do aluno passou para Blade
 * (M3), o filtro sumiu junto com a tela antiga, e o curso vencido voltou a ser
 * oferecido para matrícula. Corrigido em 21/10, agora no SERVIDOR.
 *
 * ## A comparação é entre datas, não entre instantes
 *
 * O dia do vencimento vale inteiro. `vigenciaAte` é data pura e o Carbon a lê
 * como meia-noite UTC — 21h do dia anterior em Brasília —, então comparar
 * instantes reprova o curso que vence hoje. A comparação é entre textos
 * `Y-m-d`, que ordenam corretamente.
 */
final class VigenciaDoCurso
{
    /** @param array<string, mixed> $curso Linha do curso como os services a entregam. */
    public static function vencida(array $curso): bool
    {
        $dia = self::dia($curso);

        return $dia !== null && $dia < Fuso::agora()->format('Y-m-d');
    }

    /**
     * O dia do vencimento como `Y-m-d`, ou null quando o curso não tem prazo
     * (vigência permanente).
     *
     * @param  array<string, mixed>  $curso
     */
    public static function dia(array $curso): ?string
    {
        $nativa = $curso['vigenciaAte'] ?? null;
        if ($nativa instanceof CarbonInterface) {
            return $nativa->format('Y-m-d');
        }
        if (is_string($nativa) && $nativa !== '') {
            return mb_substr($nativa, 0, 10);
        }

        // Só o texto antigo: pode estar em `Y-m-d` ou `d/m/Y`, e é o que havia
        // antes de a coluna nativa existir.
        $texto = $curso['contractExpirationDate'] ?? null;
        if (! is_string($texto) || trim($texto) === '') {
            return null;
        }

        return DataTexto::dia($texto)?->format('Y-m-d');
    }

    /**
     * O dia do vencimento no formato que uma pessoa lê, ou null.
     *
     * @param  array<string, mixed>  $curso
     */
    public static function diaParaPessoa(array $curso): ?string
    {
        $dia = self::dia($curso);
        if ($dia === null) {
            return null;
        }

        [$ano, $mes, $d] = array_pad(explode('-', $dia), 3, '');

        return $d.'/'.$mes.'/'.$ano;
    }
}
