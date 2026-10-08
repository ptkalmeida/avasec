<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    use Inativavel;

    protected $table = 'Course';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // Relações serializadas em camelCase (liveSessions, não live_sessions) para bater
    // com o contrato JSON do Node consumido pelo frontend.
    public static $snakeAttributes = false;

    protected $fillable = [
        'id', 'title', 'slug', 'description', 'category', 'thumbnail', 'instructorName', 'instructorId',
        'coverImage', 'courseType', 'hasChat', 'minAttendance', 'contractExpirationDate', 'vigenciaAte',
        'areaTematica', 'cargaHoraria', 'modalidade', 'nivel', 'emiteCertificado', 'statusCurso',
    ];

    protected $casts = [
        'hasChat' => 'boolean',
        'emiteCertificado' => 'boolean',
        'minAttendance' => 'integer',
        'cargaHoraria' => 'integer',
        /*
         * Dia real de fim de vigencia do contrato, ao lado do texto `contractExpirationDate` (Norma C.2.4). O
         * texto NAO muda: continua sendo o que a tela mostra e o que a API
         * entrega hoje.
         *
         * `date`, nao `datetime`, e SEM deslocamento de fuso: isto e data pura.
         * `Fuso` documenta a armadilha — meia-noite UTC e 21h do dia ANTERIOR em
         * Brasilia, e o dia do vencimento mudaria ao ser formatado.
         */
        'vigenciaAte' => 'date:Y-m-d',
    ];

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'courseId');
    }

    /** @return HasMany<LiveSession, $this> */
    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'courseId');
    }
}
