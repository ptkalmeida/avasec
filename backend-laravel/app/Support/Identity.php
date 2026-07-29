<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helpers de identidade por FK (ADR 10): o sub do JWT é a identidade canônica;
 * nomes são apenas display/snapshot. O fallback por nome da fase de transição
 * foi removido após backfill + purga + constraints NOT NULL.
 *
 * $requester é o array de auth_user: ['sub' => id, 'name' => ..., 'role' => ...].
 */
final class Identity
{
    /**
     * Linhas do próprio usuário, por FK apenas. Coluna parametrizada para os
     * pares divergentes do schema (studentUserId em DirectMessage, instructorId
     * em Course).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return Builder<TModel>
     */
    public static function applyOwnRows(
        Builder $query,
        array $requester,
        string $userIdColumn = 'userId',
    ): Builder {
        return $query->where($userIdColumn, $requester['sub']);
    }

    /**
     * Identidade-alvo de uma ação (ADR 10): aluno age por si (sub do token) e
     * não pode apontar terceiro; staff informa o userId do aluno-alvo, validado
     * contra um User com role student existente.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function resolveActorUserId(array $requester, ?string $explicitUserId = null): string
    {
        if ($requester['role'] === 'student') {
            if ($explicitUserId !== null && $explicitUserId !== $requester['sub']) {
                throw ApiException::forbidden('Você só pode agir em seu próprio nome.');
            }

            return $requester['sub'];
        }

        if ($explicitUserId === null || $explicitUserId === '') {
            throw ApiException::validation('Informe o userId do aluno alvo.');
        }
        $exists = User::query()->where('id', $explicitUserId)->where('role', 'student')->exists();
        if (! $exists) {
            throw ApiException::notFound('Aluno não encontrado.');
        }

        return $explicitUserId;
    }

    /**
     * Nome de exibição para snapshot em linhas satélites (studentName é display,
     * nunca identidade — ADR 10).
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function displayName(string $userId, array $requester): string
    {
        if ($userId === $requester['sub']) {
            return $requester['name'];
        }
        $name = User::query()->where('id', $userId)->value('name');

        return is_string($name) ? $name : '';
    }

    /**
     * Posse de uma linha específica, por FK apenas. Linhas órfãs (FK nula,
     * ex.: certificado de usuário deletado) não pertencem a ninguém.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function ownsRow(?string $userId, array $requester): bool
    {
        return $userId !== null && $userId !== '' && $userId === $requester['sub'];
    }
}
