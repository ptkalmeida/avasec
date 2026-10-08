<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `WebinarEvent` (schema de propriedade do Prisma durante
 * a migração). PK string gerada pela aplicação, sem timestamps.
 */
final class WebinarEvent extends Model
{
    use Inativavel;

    protected $table = 'WebinarEvent';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'title', 'date', 'dataEvento', 'time', 'description', 'link', 'image'];

    protected $casts = [
        /*
         * Instante do evento, combinando as colunas de texto `date` e `time`
         * (Norma C.2.4). As duas continuam como estao — sao o que a agenda
         * publica exibe. Com deslocamento de fuso: o horario do webinar e
         * hora de Brasilia, guardada em UTC como todo instante aqui.
         */
        'dataEvento' => 'datetime:Y-m-d\TH:i:sP',
    ];
}
