<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validação e normalização de CEP (ADR 11). Guardado só em dígitos (8), pelo
 * mesmo motivo do CPF: "20031-170" e "20031170" não podem conviver.
 *
 * Valida apenas FORMATO. Confirmar que o CEP existe de fato exigiria consultar
 * um serviço externo (ViaCEP e afins), o que a ADR 11 deixou explicitamente
 * fora de escopo — é decisão à parte.
 */
final class Cep
{
    public static function normalize(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value);

        // 8 dígitos e não pode ser tudo zero.
        return strlen($digits) === 8 && $digits !== '00000000';
    }

    /** Formata para exibição: 20031170 -> 20031-170. */
    public static function format(string $value): string
    {
        $digits = self::normalize($value);

        if (strlen($digits) !== 8) {
            return $value;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }
}
