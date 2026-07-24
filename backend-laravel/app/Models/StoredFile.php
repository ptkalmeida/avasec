<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de arquivo enviado (tabela `StoredFile`, schema do Prisma). O `id` é o nome
 * do arquivo em disco gerado pelo servidor. Sem updatedAt — createdAt é setado na criação.
 */
final class StoredFile extends Model
{
    protected $table = 'StoredFile';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'originalName', 'visibility', 'ownerUserId', 'createdAt'];
}
