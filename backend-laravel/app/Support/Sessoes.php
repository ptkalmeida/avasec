<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\Usuario;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Encerrar sessão de verdade, no servidor (ADR 022).
 *
 * Duas formas, de propósito:
 * - `encerrarTodas`: soma 1 em `Usuario.versao_da_sessao`; todo token com número menor morre.
 *   Para trocar/redefinir senha, bloquear e inativar — quando o motivo vale para a conta.
 * - `encerrarEsta`: grava o `jti` do token em `sessao_encerrada`; só aquele aparelho sai.
 *   Para o "Sair": quem sai do computador do laboratório não derruba o próprio celular.
 */
final class Sessoes
{
    /** A versão atual da sessão da conta (0 se a conta não existe). */
    public static function versaoDe(string $usuario_id): int
    {
        $v = Usuario::query()->withTrashed()->whereKey($usuario_id)->value('versao_da_sessao');

        return is_numeric($v) ? (int) $v : 0;
    }

    public static function encerrarTodas(string $usuario_id): void
    {
        Usuario::query()->withTrashed()->whereKey($usuario_id)->increment('versao_da_sessao');
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

        DB::table('sessao_encerrada')->insertOrIgnore(['jti' => $jti, 'usuario_id' => $sub, 'encerrada_em' => now()]);
    }

    /**
     * Recusa o token que foi cancelado. Chamado a cada decodificação.
     *
     * Conta inexistente não é recusada aqui: quem decide o que fazer com ela é cada
     * middleware (que distingue conta inativada de inexistente, ADR 12).
     */
    public static function conferir(string $usuario_id, int $versaoDoToken, ?string $jti): void
    {
        $atual = Usuario::query()->withTrashed()->whereKey($usuario_id)->value('versao_da_sessao');
        if (! is_numeric($atual)) {
            return;
        }

        if ($versaoDoToken < (int) $atual) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }

        if ($jti !== null && DB::table('sessao_encerrada')->where('jti', $jti)->exists()) {
            throw ApiException::unauthorized('Token inválido ou expirado.');
        }
    }
}
