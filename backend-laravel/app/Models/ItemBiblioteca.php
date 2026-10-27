<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `item_biblioteca` (de propriedade do schema Prisma durante
 * a migração — NÃO há migration Laravel para ela). PK é string gerada pela aplicação,
 * e a tabela não tem colunas created_at/updated_at.
 */
final class ItemBiblioteca extends Model
{
    use Inativavel;

    protected $table = 'item_biblioteca';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'titulo', 'tipo', 'categoria', 'descricao', 'url'];
}
