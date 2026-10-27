<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = `chave_pagina` — uma linha por página pública editável.
 */
final class ConteudoPaginaSite extends Model
{
    use Inativavel;

    protected $table = 'conteudo_pagina_site';

    protected $primaryKey = 'chave_pagina';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'chave_pagina', 'conteudo', 'atualizado_por_usuario_id', 'atualizado_em',
    ];

    protected $casts = [
        'conteudo' => 'array',
        'atualizado_em' => 'datetime',
    ];
}
