<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Quem está vendo esta página pública — se é que alguém está logado.
 *
 * É a versão web do `OptionalJwt`: nunca barra, nunca redireciona. Só deixa
 * `$usuarioLogado` disponível para as views, para o cabeçalho do portal poder
 * oferecer "Meu painel" a quem já entrou em vez de "Entrar".
 *
 * Isso não é enfeite: no React, o cabeçalho trocava os dois botões por um
 * atalho ao painel quando havia sessão. Sem este middleware, a pessoa logada
 * veria "Entrar" na home e concluiria que a sessão caiu.
 *
 * **Toda falha é silêncio de propósito.** Token expirado, adulterado, conta
 * inativada ou inexistente resultam em `null` — que é exatamente o estado
 * "visitante". Numa página pública não há nada a comunicar sobre isso: quem
 * precisa da sessão é a área autenticada, e lá quem decide é `AreaDoAluno`.
 */
final class IdentidadeOpcional
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('usuarioLogado', $this->identidade($request));

        return $next($request);
    }

    /** @return array{sub:string,name:string,role:string}|null */
    private function identidade(Request $request): ?array
    {
        try {
            $identidade = JwtAuthenticate::decode(JwtAuthenticate::extractToken($request));
        } catch (Throwable) {
            return null;
        }

        if ($identidade === null) {
            return null;
        }

        // Papel e situação vêm do banco, nunca do token (que vale 12 h). Conta
        // inativada é visitante: `withTrashed` para distingui-la de inexistente.
        $usuario = User::query()->withTrashed()->find($identidade['sub'], ['id', 'name', 'role', 'status', 'inativadoEm']);

        if ($usuario === null || $usuario->estaInativo() || $usuario->status !== 'active') {
            return null;
        }

        return [
            'sub' => (string) $usuario->id,
            'name' => (string) $usuario->name,
            'role' => (string) $usuario->role,
        ];
    }
}
