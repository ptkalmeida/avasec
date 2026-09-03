<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class QuizSubmission extends Model
{
    use Inativavel;

    protected $table = 'QuizSubmission';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'userId', 'courseId', 'quizId', 'scorePercent', 'passed', 'submittedAt'];

    protected $casts = ['scorePercent' => 'float', 'passed' => 'boolean'];
}
