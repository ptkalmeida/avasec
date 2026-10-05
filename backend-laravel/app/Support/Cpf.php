<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validação e normalização de CPF (ADR 11).
 *
 * O CPF é guardado no banco SEMPRE normalizado (11 dígitos, sem pontuação) —
 * é o que torna o índice único confiável e a busca no login determinística:
 * "529.982.247-25" e "52998224725" precisam colidir, não conviver.
 *
 * Sem dependência externa de propósito: o algoritmo do dígito verificador é
 * público, estável desde sempre e cabe aqui, testado.
 */
final class Cpf
{
    /** Mantém só os dígitos (descarta pontos, traços e espaços). */
    public static function normalize(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Valida formato e os dois dígitos verificadores.
     *
     * Sequências de dígito repetido (00000000000, 11111111111, ...) são
     * rejeitadas explicitamente: elas passam no cálculo por acidente
     * matemático, mas nenhuma é um CPF real.
     */
    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value);

        if (strlen($digits) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        // Posição 9 = 1º dígito verificador, posição 10 = 2º.
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digits[$i] * (($position + 1) - $i);
            }

            $expected = ((10 * $sum) % 11) % 10;

            if ((int) $digits[$position] !== $expected) {
                return false;
            }
        }

        return true;
    }

    /** Formata para exibição: 52998224725 -> 529.982.247-25. */
    public static function format(string $value): string
    {
        $digits = self::normalize($value);

        if (strlen($digits) !== 11) {
            return $value;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'
            .substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }
}
