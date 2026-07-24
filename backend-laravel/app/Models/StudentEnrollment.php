<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A PK desta tabela é `studentName` (design legado); `id` é um identificador estável
 * secundário (unique) usado como alvo das FKs de progresso/certificado.
 */
final class StudentEnrollment extends Model
{
    protected $table = 'StudentEnrollment';

    protected $primaryKey = 'studentName';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'studentName', 'id', 'userId', 'enrolledCourseId', 'enrolledAt',
        'completedCourseIds', 'dropOutPenaltyUntil',
    ];

    protected $casts = [
        'completedCourseIds' => 'array',
    ];
}
