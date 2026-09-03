<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use App\Support\BusinessRules;
use Illuminate\Database\Eloquent\Model;

final class LiveSession extends Model
{
    use Inativavel;

    protected $table = 'LiveSession';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'title', 'scheduledAt', 'durationMinutes', 'meetingLink', 'isLive'];

    protected $casts = [
        'durationMinutes' => 'integer',
        'isLive' => 'boolean',
    ];

    /**
     * A transmissão passou da janela de encerramento automático.
     *
     * `isLive` é um interruptor MANUAL: quem dá aula clica "Iniciar" e precisa
     * clicar "Finalizar". Quando esquece, a sessão fica ao vivo para sempre —
     * aconteceu com dois encontros de 01/09, ainda marcados ao vivo dois dias
     * depois, com sala aberta e ninguém dentro.
     *
     * A contagem parte do horário AGENDADO, não do clique em "Iniciar": não há
     * registro de quando a transmissão começou de fato.
     */
    public function encerradaPorTempo(): bool
    {
        return BusinessRules::liveSessionExpired(
            is_string($this->scheduledAt) ? $this->scheduledAt : null
        );
    }

    /**
     * Serialização com a regra das 24h aplicada.
     *
     * Sobrescrito em vez de accessor de propósito: com colunas em camelCase, o
     * `$snakeAttributes` do Eloquent procuraria `is_live` e o accessor NUNCA
     * seria aplicado em `toArray()` — a API continuaria respondendo
     * `isLive: true` para uma sessão de três dias atrás. Aqui é explícito.
     *
     * O valor gravado não é reescrito: ele registra o que a pessoa clicou, e a
     * verdade exibida é derivada dele + o tempo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dados = parent::toArray();
        if ($this->encerradaPorTempo()) {
            $dados['isLive'] = false;
        }

        return $dados;
    }
}
