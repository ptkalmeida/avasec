<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    protected $table = 'Course';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // Relações serializadas em camelCase (liveSessions, não live_sessions) para bater
    // com o contrato JSON do Node consumido pelo frontend.
    public static $snakeAttributes = false;

    protected $fillable = [
        'id', 'title', 'description', 'category', 'thumbnail', 'instructorName', 'instructorId',
        'coverImage', 'courseType', 'hasChat', 'minAttendance', 'contractExpirationDate',
        'areaTematica', 'cargaHoraria', 'modalidade', 'nivel', 'emiteCertificado', 'statusCurso',
    ];

    protected $casts = [
        'hasChat' => 'boolean',
        'emiteCertificado' => 'boolean',
        'minAttendance' => 'integer',
        'cargaHoraria' => 'integer',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'courseId');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'courseId');
    }
}
