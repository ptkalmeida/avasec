<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `evento_webinar` (schema de propriedade do Prisma durante
 * a migração). PK string gerada pela aplicação, sem timestamps.
 */
final class EventoWebinar extends Model
{
    use Inativavel;

    protected $table = 'evento_webinar';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'titulo', 'data_texto', 'data_evento', 'horario', 'descricao', 'link', 'imagem'];

    protected $casts = [
        /*
         * Instante do evento, combinando as colunas de texto `date` e `time`
         * (Norma C.2.4). As duas continuam como estao — sao o que a agenda
         * publica exibe. Com deslocamento de fuso: o horario do webinar e
         * hora de Brasilia, guardada em UTC como todo instante aqui.
         */
        'data_evento' => 'datetime:Y-m-d\TH:i:sP',
    ];
}
