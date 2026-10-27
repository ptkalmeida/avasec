<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class Certificado extends Model
{
    use Inativavel;

    protected $table = 'certificado';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'nome_aluno', 'usuario_id', 'matricula_id', 'curso_id',
        'titulo_curso', 'data_emissao', 'percentual_frequencia', 'hash_verificacao',
    ];

    protected $casts = [
        'percentual_frequencia' => 'float',
        // Armazenado como DATE, mas serializado no contrato como d/m/Y (frontend
        // exibe direto, sem new Date). Ordenação passa a ser cronológica correta.
        'data_emissao' => 'date:d/m/Y',
    ];
}
