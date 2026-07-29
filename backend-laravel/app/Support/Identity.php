<?php

declare(strict_types=1);

namespace App\Support;

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
