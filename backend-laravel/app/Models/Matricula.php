<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = usuario_id (ADR 10 — identidade por FK). `nome_aluno` é snapshot de exibição;
 * `id` é um identificador estável secundário (unique) usado como alvo das FKs de
 * progresso/certificado.
 */
final class Matricula extends Model
{
    use Inativavel;

    protected $table = 'matricula';

    protected $primaryKey = 'usuario_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nome_aluno', 'id', 'usuario_id', 'curso_matriculado_id', 'matriculado_em_texto',
        'cursos_concluidos_ids', 'penalidade_desistencia_ate', 'pode_multimatricula', 'cursos_extras_ids',
    ];

    protected $casts = [
        'cursos_concluidos_ids' => 'array',
        'cursos_extras_ids' => 'array',
        'pode_multimatricula' => 'bool',
    ];
}
