<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class EntregaExercicio extends Model
{
    use Inativavel;

    protected $table = 'entrega_exercicio';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'exercicio_id', 'nome_aluno', 'usuario_id', 'texto_entrega', 'url_arquivo', 'nome_arquivo',
        'enviado_em_texto', 'status', 'nota', 'devolutiva', 'corrigido_em_texto', 'corrigido_por',
    ];

    protected $casts = ['nota' => 'integer'];
}
