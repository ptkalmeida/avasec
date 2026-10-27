<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `usuario` (schema de propriedade do Prisma durante a
 * migração). PK é cuid (string), timestamps em camelCase (criado_em/atualizado_em) como
 * o Prisma gera. A verificação/geração de senha NÃO usa o Hash facade do Laravel
 * (que rejeita o prefixo $2a$ do bcryptjs) — ver AuthService, que usa as funções
 * nativas password_verify/password_hash para interoperar com o Node.
 */
final class Usuario extends Model
{
    use Inativavel;

    protected $table = 'usuario';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $fillable = [
        'id', 'nome', 'email', 'senha_hash', 'papel', 'status',
        'cpf', 'municipio', 'uf', 'area_interesse', 'data_cadastro_texto',
        // Dados cadastrais completos do aluno (ADR 11).
        'celular', 'cep', 'endereco', 'nome_social', 'identidade',
        'tentativas_login_falhas', 'bloqueado_ate',
    ];

    protected $hidden = ['senha_hash'];

    protected $casts = [
        'tentativas_login_falhas' => 'integer',
        'bloqueado_ate' => 'immutable_datetime',
        'criado_em' => 'datetime',
        'atualizado_em' => 'datetime',
    ];
}
