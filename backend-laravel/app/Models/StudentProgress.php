<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class StudentProgress extends Model
{
    protected $table = 'StudentProgress';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'studentName', 'userId', 'enrollmentId', 'courseId',
        'completedLessons', 'attendedLiveSessions',
    ];

    protected $casts = [
        'completedLessons' => 'array',
        'attendedLiveSessions' => 'array',
    ];
}
