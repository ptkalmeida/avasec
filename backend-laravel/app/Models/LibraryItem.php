<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `LibraryItem` (de propriedade do schema Prisma durante
 * a migração — NÃO há migration Laravel para ela). PK é string gerada pela aplicação,
 * e a tabela não tem colunas created_at/updated_at.
 */
final class LibraryItem extends Model
{
    protected $table = 'LibraryItem';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'title', 'type', 'category', 'description', 'url'];
}
