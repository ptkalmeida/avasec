<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = userId (ADR 10 — identidade por FK). `studentName` é snapshot de exibição;
 * `id` é um identificador estável secundário (unique) usado como alvo das FKs de
 * progresso/certificado.
 */
final class StudentEnrollment extends Model
{
    use Inativavel;

    protected $table = 'StudentEnrollment';

    protected $primaryKey = 'userId';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'studentName', 'id', 'userId', 'enrolledCourseId', 'enrolledAt',
        'completedCourseIds', 'dropOutPenaltyUntil', 'canMultiEnroll', 'extraCourseIds',
    ];

    protected $casts = [
        'completedCourseIds' => 'array',
        'extraCourseIds' => 'array',
        'canMultiEnroll' => 'bool',
    ];
}
