<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de arquivo enviado (tabela `arquivo_armazenado`, schema do Prisma). O `id` é o nome
 * do arquivo em disco gerado pelo servidor. Sem atualizado_em — criado_em é setado na criação.
 */
final class ArquivoArmazenado extends Model
{
    use Inativavel;

    protected $table = 'arquivo_armazenado';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'nome_original', 'visibilidade', 'dono_usuario_id', 'criado_em'];
}
