<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LogSeguranca extends Model
{
    protected $table = 'log_seguranca';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'registrado_em_texto', 'ocorrido_em', 'usuario', 'papel', 'ip', 'dispositivo', 'acao', 'detalhes', 'status'];

    protected $casts = [
        /*
         * Instante real de ocorrencia, ao lado do texto de exibicao `timestamp`
         * (Norma C.2.4). O texto NAO muda: continua sendo o que a tela mostra.
         * Esta coluna e o eixo de ordenacao e comparacao.
         *
         * Serializada COM deslocamento de fuso: a aplicacao guarda em UTC e o
         * navegador de quem le, nao. Sem o sufixo, o JavaScript interpretaria o
         * valor como hora local e deslocaria tudo em 3 horas.
         */
        'ocorrido_em' => 'datetime:Y-m-d\TH:i:sP',
    ];
}
