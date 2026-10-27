<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EventoCliente extends Model
{
    protected $table = 'evento_cliente';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'criado_em', 'usuario', 'papel', 'ip', 'dispositivo', 'acao', 'detalhes', 'status'];

    protected $casts = ['criado_em' => 'datetime'];
}
