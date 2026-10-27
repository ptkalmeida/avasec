<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class ExercicioPratico extends Model
{
    use Inativavel;

    protected $table = 'exercicio_pratico';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'curso_id', 'titulo', 'descricao', 'instrucoes', 'pontuacao_maxima', 'prazo_texto', 'prazo_em'];

    protected $casts = [
        'pontuacao_maxima' => 'integer',
        /*
         * Dia real de entrega, ao lado do texto `prazo_texto` (Norma C.2.4). O
         * texto NAO muda: continua sendo o que a tela mostra e o que a API
         * entrega hoje.
         *
         * `date`, nao `datetime`, e SEM deslocamento de fuso: isto e data pura.
         * `Fuso` documenta a armadilha — meia-noite UTC e 21h do dia ANTERIOR em
         * Brasilia, e o dia do prazo mudaria ao ser formatado.
         */
        'prazo_em' => 'date:Y-m-d',
    ];
}
