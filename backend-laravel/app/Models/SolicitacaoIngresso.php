<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class SolicitacaoIngresso extends Model
{
    use Inativavel;

    protected $table = 'solicitacao_ingresso';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'nome_aluno', 'usuario_id', 'curso_id', 'status', 'enviado_em_texto'];
}
