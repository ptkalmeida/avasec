<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Mapeia a tabela existente `User` (schema de propriedade do Prisma durante a
 * migração). PK é cuid (string), timestamps em camelCase (createdAt/updatedAt) como
 * o Prisma gera. A verificação/geração de senha NÃO usa o Hash facade do Laravel
 * (que rejeita o prefixo $2a$ do bcryptjs) — ver AuthService, que usa as funções
 * nativas password_verify/password_hash para interoperar com o Node.
 */
final class User extends Model
{
    use Inativavel;

    protected $table = 'User';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id', 'name', 'email', 'passwordHash', 'role', 'status',
        'cpf', 'municipio', 'uf', 'areaInteresse', 'dataCadastro',
        // Dados cadastrais completos do aluno (ADR 11).
        'celular', 'cep', 'endereco', 'nomeSocial', 'identidade',
        'failedLoginAttempts', 'lockedUntil',
    ];

    protected $hidden = ['passwordHash'];

    protected $casts = [
        'failedLoginAttempts' => 'integer',
        'lockedUntil' => 'immutable_datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}
