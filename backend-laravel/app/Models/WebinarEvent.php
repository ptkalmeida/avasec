<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `WebinarEvent` (schema de propriedade do Prisma durante
 * a migração). PK string gerada pela aplicação, sem timestamps.
 */
final class WebinarEvent extends Model
{
    protected $table = 'WebinarEvent';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'title', 'date', 'time', 'description', 'link', 'image'];
}
