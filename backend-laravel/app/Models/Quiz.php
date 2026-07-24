<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Quiz extends Model
{
    protected $table = 'Quiz';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'title'];

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quizId');
    }
}
