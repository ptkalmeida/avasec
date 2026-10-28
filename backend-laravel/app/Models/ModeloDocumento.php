<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = `type` — uma linha por tipo de documento (certificado, historico).
 */
final class ModeloDocumento extends Model
{
    use Inativavel;

    protected $table = 'modelo_documento';

    protected $primaryKey = 'tipo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'tipo', 'nome_instituicao', 'titulo', 'texto_corpo', 'rotulos_colunas', 'caminho_logo_instituicao', 'signatarios',
        'texto_rodape', 'html_personalizado', 'atualizado_por_usuario_id', 'atualizado_em',
    ];

    protected $casts = [
        'signatarios' => 'array',
        'rotulos_colunas' => 'array',
        'atualizado_em' => 'datetime',
    ];
}
