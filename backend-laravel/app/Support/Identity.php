<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helpers de identidade por FK (ADR 10): o sub do JWT é a identidade canônica;
 * nomes são apenas display/snapshot. O fallback por nome da fase de transição
 * foi removido após backfill + purga + constraints NOT NULL.
 *
 * $requester é o array de auth_user: ['sub' => id, 'nome' => ..., 'papel' => ...].
 */
final class Identity
{
    /**
     * Linhas do próprio usuário, por FK apenas. Coluna parametrizada para os
     * pares divergentes do schema (aluno_usuario_id em MensagemDireta, instrutor_id
     * em Curso).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return Builder<TModel>
     */
    public static function applyOwnRows(
        Builder $query,
        array $requester,
        string $userIdColumn = 'usuario_id',
    ): Builder {
        return $query->where($userIdColumn, $requester['sub']);
    }

    /**
     * Identidade-alvo de uma ação (ADR 10): aluno age por si (sub do token) e
     * não pode apontar terceiro; staff informa o usuario_id do aluno-alvo, validado
     * contra um Usuario com role student existente.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public static function resolveActorUserId(array $requester, ?string $explicitUserId = null): string
    {
        if ($requester['papel'] === 'student') {
            if ($explicitUserId !== null && $explicitUserId !== $requester['sub']) {
                throw ApiException::forbidden('Você só pode agir em seu próprio nome.');
            }

            return $requester['sub'];
        }

        if ($explicitUserId === null || $explicitUserId === '') {
            throw ApiException::validation('Informe o usuario_id do aluno alvo.');
        }
        $exists = Usuario::query()->where('id', $explicitUserId)->where('papel', 'student')->exists();
        if (! $exists) {
            throw ApiException::notFound('Aluno não encontrado.');
        }

        return $explicitUserId;
    }

    /**
     * Nome de exibição para snapshot em linhas satélites (nome_aluno é display,
     * nunca identidade — ADR 10).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public static function displayName(string $usuario_id, array $requester): string
    {
        if ($usuario_id === $requester['sub']) {
            return $requester['nome'];
        }
        $name = Usuario::query()->where('id', $usuario_id)->value('nome');

        return is_string($name) ? $name : '';
    }

    /**
     * Posse de uma linha específica, por FK apenas. Linhas órfãs (FK nula,
     * ex.: certificado de usuário deletado) não pertencem a ninguém.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public static function ownsRow(?string $usuario_id, array $requester): bool
    {
        return $usuario_id !== null && $usuario_id !== '' && $usuario_id === $requester['sub'];
    }
}
