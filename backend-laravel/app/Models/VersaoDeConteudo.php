<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma versão anterior de página do site, modelo de documento ou aula (ADR 021).
 *
 * Só se acrescenta: não há `Inativavel` aqui porque nada nesta tabela sai do ar —
 * ela É o registro do que saiu. Atualizar ou apagar uma linha é proibido.
 *
 * @property string $id
 * @property string $tipo
 * @property string $referencia
 * @property string $conteudo JSON do conteúdo que existia antes da alteração
 * @property string $origem 'edicao' ou 'restauracao'
 * @property string|null $criadoPorUserId
 * @property Carbon|null $criadoEm
 */
final class VersaoDeConteudo extends Model
{
    protected $table = 'VersaoDeConteudo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'tipo', 'referencia', 'conteudo', 'origem', 'criadoPorUserId', 'criadoEm'];

    protected $casts = ['criadoEm' => 'datetime'];
}
