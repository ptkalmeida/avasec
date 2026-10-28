<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texto do corpo dos documentos (certificado, histórico) com campos automáticos.
 *
 * O admin escreve texto puro com `{aluno}`, `{curso}`... O texto inteiro é escapado
 * ANTES de os campos serem trocados, e cada valor é escapado ao entrar: nada digitado
 * vira marcação no PDF (o campo de HTML livre continua fora da tela, ADR 21).
 */
final class TextoDoDocumento
{
    /** Campo => descrição mostrada na tela. */
    public const CAMPOS = [
        'aluno' => 'Nome do aluno',
        'curso' => 'Título do curso',
        'carga_horaria' => 'Carga horária (ex.: 40 horas)',
        'frequencia' => 'Frequência (ex.: 100%)',
        'data' => 'Data de emissão',
    ];

    /** Campos que cada tipo de documento sabe preencher. */
    public const CAMPOS_POR_TIPO = [
        'certificado' => ['aluno', 'curso', 'carga_horaria', 'frequencia', 'data'],
        'historico' => ['aluno', 'data'],
    ];

    /**
     * Campos escritos entre chaves que o tipo não conhece.
     *
     * @return list<string>
     */
    public static function camposDesconhecidos(string $texto, string $tipo): array
    {
        preg_match_all('/\{([^{}\s]*)\}/u', $texto, $achados);
        $aceitos = self::CAMPOS_POR_TIPO[$tipo] ?? [];

        return array_values(array_unique(array_filter(
            $achados[1],
            static fn (string $campo): bool => ! in_array($campo, $aceitos, true),
        )));
    }

    /**
     * HTML seguro: parágrafos separados por linha em branco, campos trocados pelo valor.
     *
     * @param  array<string, string|null>  $valores
     */
    public static function renderizar(string $texto, array $valores): string
    {
        $pares = [];
        foreach ($valores as $campo => $valor) {
            $classe = in_array($campo, ['aluno', 'curso'], true) ? 'campo-'.$campo : 'campo-destaque';
            $pares['{'.$campo.'}'] = '<span class="'.$classe.'">'.e($valor ?? '—').'</span>';
        }

        $paragrafos = preg_split('/\R{2,}/u', trim($texto)) ?: [];
        $html = '';
        foreach ($paragrafos as $paragrafo) {
            $paragrafo = trim($paragrafo);
            if ($paragrafo === '') {
                continue;
            }
            $html .= '<p class="lead">'.nl2br(strtr(e($paragrafo), $pares), false).'</p>';
        }

        return $html;
    }
}
