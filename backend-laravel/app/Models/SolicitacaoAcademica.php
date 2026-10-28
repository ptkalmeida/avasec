<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class SolicitacaoAcademica extends Model
{
    use Inativavel;

    protected $table = 'solicitacao_academica';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'nome_aluno', 'usuario_id', 'tipo', 'descricao', 'status', 'enviado_em_texto', 'titulo_curso'];
}
