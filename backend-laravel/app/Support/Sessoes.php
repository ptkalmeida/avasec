<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\User;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Encerrar sessão de verdade, no servidor (ADR 022).
 *
 * Duas formas, de propósito:
 * - `encerrarTodas`: soma 1 em `User.versaoDaSessao`; todo token com número menor morre.
 *   Para trocar/redefinir senha, bloquear e inativar — quando o motivo vale para a conta.
 * - `encerrarEsta`: grava o `jti` do token em `SessaoEncerrada`; só aquele aparelho sai.
 *   Para o "Sair": quem sai do computador do laboratório não derruba o próprio celular.
 */
final class Sessoes
{
    /** A versão atual da sessão da conta (0 se a conta não existe). */
    public static function versaoDe(string $userId): int
    {
        $v = User::query()->withTrashed()->whereKey($userId)->value('versaoDaSessao');

        return is_numeric($v) ? (int) $v : 0;
    }

    public static function encerrarTodas(string $userId): void
    {
        User::query()->withTrashed()->whereKey($userId)->increment('versaoDaSessao');
    }

    /** O "Sair" deste aparelho: cancela só o token apresentado. Token inválido não é erro. */
    public static function encerrarEsta(?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        try {
            $claims = FirebaseJwt::decode($token, new Key(Jwt::secret(), 'HS256'));
        } catch (Throwable) {
            return;
        }

        $jti = $claims->jti ?? null;
        $sub = $claims->sub ?? null;
        if (! is_string($jti) || $jti === '' || ! is_string($sub)) {
            // Token anterior a esta versão não tem `jti`: só expira. Vale ≤ 12 h.
            return;
        }

        DB::table('SessaoEncerrada')->insertOrIgnore(['jti' => $jti, 'userId' => $sub, 'encerradaEm' => now()]);
    }

    /**
     * Recusa o token que foi cancelado. Chamado a cada decodificação.
     *
     * Conta inexistente não é recusada aqui: quem decide o que fazer com ela é cada
     * middleware (que distingue conta inativada de inexistente, ADR 12).
     */
    public static function conferir(string $userId, int $versaoDoToken, ?string $jti): void
    {
        $atual = User::query()->withTrashed()->whereKey($userId)->value('versaoDaSessao');
        if (! is_numeric($atual)) {
            return;
        }

        if ($versaoDoToken < (int) $atual) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        if ($jti !== null && DB::table('SessaoEncerrada')->where('jti', $jti)->exists()) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }
    }
}
