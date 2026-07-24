<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class QuizSubmission extends Model
{
    protected $table = 'QuizSubmission';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'userId', 'courseId', 'quizId', 'scorePercent', 'passed', 'submittedAt'];

    protected $casts = ['scorePercent' => 'float', 'passed' => 'boolean'];
}
