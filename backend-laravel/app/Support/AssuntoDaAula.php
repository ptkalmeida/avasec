<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O prefixo `[Aula: título]` das mensagens de suporte.
 *
 * O formato nasceu no cartão de suporte do React (`comAssuntoDaAula`) e é o
 * único vínculo entre uma dúvida e a aula de onde ela veio — não há coluna de
 * aula em `mensagem_direta`. Enquanto não houver, quem monta e quem lê o prefixo
 * precisam concordar, e por isso as duas pontas moram aqui.
 *
 * A fragilidade está registrada de propósito: um aluno que escreva um título
 * começando com `[Aula: ...]` produz a mesma marca. O dano é só de rótulo — a
 * mensagem continua chegando a quem deve, e nenhuma permissão depende disto.
 * A saída definitiva é uma coluna `aula_id`, que é mudança de schema e pede
 * validação humana (AGENTS.md).
 */
final class AssuntoDaAula
{
    /** Põe o assunto na frente do texto, como o React fazia. */
    public static function montar(?string $tituloDaAula, string $texto): string
    {
        $titulo = trim((string) $tituloDaAula);

        return $titulo === '' ? $texto : '[Aula: '.$titulo.'] '.$texto;
    }

    /**
     * Separa o assunto do corpo. Sem marca, `aula` é nulo e o texto volta
     * inteiro.
     *
     * @return array{aula:string|null,texto:string}
     */
    public static function separar(string $bruto): array
    {
        if (preg_match('/^\[Aula:\s*(.+?)\]\s*(.*)$/su', $bruto, $partes) !== 1) {
            return ['aula' => null, 'texto' => $bruto];
        }

        $titulo = trim($partes[1]);

        return [
            'aula' => $titulo === '' ? null : $titulo,
            'texto' => $partes[2],
        ];
    }
}
