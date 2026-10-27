<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Curso extends Model
{
    use Inativavel;

    protected $table = 'curso';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'titulo', 'slug', 'descricao', 'categoria', 'miniatura', 'nome_instrutor', 'instrutor_id',
        'imagem_capa', 'tipo_curso', 'tem_chat', 'frequencia_minima', 'vencimento_contrato_texto', 'vigencia_ate',
        'area_tematica', 'carga_horaria', 'modalidade', 'nivel', 'emite_certificado', 'status_curso',
    ];

    protected $casts = [
        'tem_chat' => 'boolean',
        'emite_certificado' => 'boolean',
        'frequencia_minima' => 'integer',
        'carga_horaria' => 'integer',
        /*
         * Dia real de fim de vigencia do contrato, ao lado do texto `vencimento_contrato_texto` (Norma C.2.4). O
         * texto NAO muda: continua sendo o que a tela mostra e o que a API
         * entrega hoje.
         *
         * `date`, nao `datetime`, e SEM deslocamento de fuso: isto e data pura.
         * `Fuso` documenta a armadilha — meia-noite UTC e 21h do dia ANTERIOR em
         * Brasilia, e o dia do vencimento mudaria ao ser formatado.
         */
        'vigencia_ate' => 'date:Y-m-d',
    ];

    /** @return HasMany<Aula, $this> */
    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'curso_id');
    }

    /** @return HasMany<SessaoAoVivo, $this> */
    public function sessoesAoVivo(): HasMany
    {
        return $this->hasMany(SessaoAoVivo::class, 'curso_id');
    }
}
