<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra de validação para URL que termina num href/src do navegador.
 * A lógica fica em App\Support\SafeUrl, espelhada em src/utils/safeUrl.ts.
 */
final class SafeUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SafeUrl::isValid($value)) {
            $fail('O campo :attribute deve ser uma URL http(s) válida ou um caminho interno começando com "/".');
        }
    }
}
