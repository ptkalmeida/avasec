<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class MensagemDireta extends Model
{
    use Inativavel;

    protected $table = 'mensagem_direta';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'nome_aluno', 'aluno_usuario_id', 'nome_remetente', 'remetente_usuario_id', 'papel_remetente', 'texto', 'registrado_em_texto', 'enviada_em'];

    protected $casts = [
        /*
         * Instante real de envio, ao lado do texto de exibicao `timestamp`
         * (Norma C.2.4). O texto NAO muda: continua sendo o que a tela mostra.
         * Esta coluna e o eixo de ordenacao e comparacao.
         *
         * Serializada COM deslocamento de fuso: a aplicacao guarda em UTC e o
         * navegador de quem le, nao. Sem o sufixo, o JavaScript interpretaria o
         * valor como hora local e deslocaria tudo em 3 horas.
         */
        'enviada_em' => 'datetime:Y-m-d\TH:i:sP',
    ];
}
