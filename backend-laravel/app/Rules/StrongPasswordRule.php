<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Política de senha real (ADR 11): mínimo 8 caracteres, com ao menos uma letra
 * e um dígito. Substitui o PIN numérico de 4 dígitos do MVP de demonstração.
 *
 * Implementa `ValidationRule` (e não usa `Illuminate\Validation\Rules\Password`)
 * porque aquela classe implementa o contrato legado `Rule`, incompatível com a
 * tipagem de `ApiRequestHelpers::validateInput` — e porque manter a regra aqui
 * a torna testável e consistente com as outras regras do projeto.
 */
final class StrongPasswordRule implements ValidationRule
{
    public const MIN_LENGTH = 8;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < self::MIN_LENGTH) {
            $fail('A senha deve ter no mínimo '.self::MIN_LENGTH.' caracteres.');

            return;
        }

        if (preg_match('/\p{L}/u', $value) !== 1) {
            $fail('A senha deve conter ao menos uma letra.');

            return;
        }

        if (preg_match('/\d/', $value) !== 1) {
            $fail('A senha deve conter ao menos um número.');
        }
    }
}
