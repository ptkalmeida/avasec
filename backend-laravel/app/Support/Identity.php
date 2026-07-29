<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helpers de identidade da fase de transição nome -> FK — espelha
 * src/server/utils/identity.ts. Linhas novas gravam a FK (userId); a autorização
 * usa a FK quando existe e só cai para o nome em linhas legadas (userId nulo).
 *
 * $requester é o array de auth_user: ['sub' => id, 'name' => ..., 'role' => ...].
 */
final class Identity
{
    /**
     * Resolve o User.id de um aluno pelo nome (para ações de admin em nome de terceiros).
     * Se o requester é o próprio aluno, usa o id do token sem consultar o banco.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function resolveStudentUserId(string $studentName, array $requester): ?string
    {
        if (($requester['role'] ?? null) === 'student' && ($requester['name'] ?? null) === $studentName) {
            return $requester['sub'];
        }

        $id = User::query()
            ->where('name', $studentName)
            ->where('role', 'student')
            ->value('id');

        return is_string($id) ? $id : null;
    }

    /**
     * Aplica a cláusula transicional: linhas do próprio usuário por FK OU linhas
     * legadas (FK nula) que casam pelo nome. Impede que uma linha já associada a
     * outro userId seja lida por alguém com o mesmo nome de exibição.
     *
     * Colunas parametrizadas para cobrir os pares divergentes do schema
     * (studentUserId/studentName em DirectMessage, instructorId/instructorName
     * em Course) com uma única implementação.
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
        string $nameColumn = 'studentName',
    ): Builder {
        return $query->where(function (Builder $q) use ($requester, $userIdColumn, $nameColumn): void {
            $q->where($userIdColumn, $requester['sub'])
                ->orWhere(function (Builder $q2) use ($requester, $userIdColumn, $nameColumn): void {
                    $q2->whereNull($userIdColumn)->where($nameColumn, $requester['name']);
                });
        });
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
     * Posse de uma linha específica: FK primeiro, nome como fallback legado.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public static function ownsRow(?string $userId, ?string $studentName, array $requester): bool
    {
        if ($userId !== null && $userId !== '') {
            return $userId === $requester['sub'];
        }

        return $studentName === ($requester['name'] ?? null);
    }
}
