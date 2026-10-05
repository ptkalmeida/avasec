<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class QuizQuestion extends Model
{
    use Inativavel;

    protected $table = 'QuizQuestion';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'quizId', 'questionText', 'options', 'correctOptionIndex',
        'explanation', 'reviewMessage', 'recommendedModule', 'allowRetry',
    ];

    protected $casts = [
        'options' => 'array',
        'correctOptionIndex' => 'integer',
        'allowRetry' => 'boolean',
    ];
}
