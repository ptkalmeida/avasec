<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\VideoSource;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class VideoUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! VideoSource::isValid($value)) {
            $fail('O campo :attribute deve ser uma URL de vídeo do YouTube ou um arquivo de vídeo (mp4, webm, ogg, m4v, mov).');
        }
    }
}
