<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class QuestaoAvaliacao extends Model
{
    use Inativavel;

    protected $table = 'questao_avaliacao';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'avaliacao_id', 'texto_questao', 'opcoes', 'indice_opcao_correta',
        'explicacao', 'mensagem_revisao', 'modulo_recomendado', 'permite_nova_tentativa',
    ];

    protected $casts = [
        'opcoes' => 'array',
        'indice_opcao_correta' => 'integer',
        'permite_nova_tentativa' => 'boolean',
    ];
}
