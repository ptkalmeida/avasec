<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Cep;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CepRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cep::isValid($value)) {
            $fail('O campo :attribute deve ser um CEP válido (8 dígitos).');
        }
    }
}
