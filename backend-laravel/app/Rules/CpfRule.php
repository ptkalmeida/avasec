<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CpfRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cpf::isValid($value)) {
            $fail('O campo :attribute deve ser um CPF válido.');
        }
    }
}
