<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Certificate extends Model
{
    protected $table = 'Certificate';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'studentName', 'userId', 'enrollmentId', 'courseId',
        'courseTitle', 'issueDate', 'attendancePercent', 'verificationHash',
    ];

    protected $casts = [
        'attendancePercent' => 'float',
    ];
}
