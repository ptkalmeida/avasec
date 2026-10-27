<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class ProgressoAluno extends Model
{
    use Inativavel;

    protected $table = 'progresso_aluno';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'nome_aluno', 'usuario_id', 'matricula_id', 'curso_id',
        'aulas_concluidas', 'sessoes_ao_vivo_assistidas',
    ];

    protected $casts = [
        'aulas_concluidas' => 'array',
        'sessoes_ao_vivo_assistidas' => 'array',
    ];
}
