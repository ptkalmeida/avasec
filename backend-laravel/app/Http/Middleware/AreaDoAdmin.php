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
 * Quem está vendo a tela do administrador — para rotas **web**, não para a API.
 *
 * Irmão do `AreaDoInstrutor`, pelas mesmas razões: lê o cookie `ava_session`
 * que já existe, reconfere a conta no banco (nunca no token, que vale 12 h) e
 * manda ao login quem não pode entrar.
 *
 * ## Só o administrador entra
 *
 * Ao contrário da área do instrutor, que aceita o admin, aqui o caminho é de
 * mão única: o painel é do admin, e o instrutor e o aluno que digitarem
 * `/admin` vão para a área deles, onde têm o que fazer — não para uma página de
 * erro. O papel é o do BANCO: um admin rebaixado não entra com o token antigo.
 */
final class AreaDoAdmin
{
    private const LOGIN = '/entrar';

    public function handle(Request $request, Closure $next): Response
    {
        $token = JwtAuthenticate::extractToken($request);

        try {
            $identidade = JwtAuthenticate::decode($token);
        } catch (Throwable) {
            return redirect(self::LOGIN);
        }

        if ($identidade === null) {
            return redirect(self::LOGIN);
        }

        // `withTrashed` pela ADR 12: conta inativada some das consultas padrão
        // e cairia no ramo "não existe".
        $usuario = User::query()->withTrashed()->find($identidade['sub'], ['id', 'name', 'role', 'status', 'inativadoEm']);

        if ($usuario === null || $usuario->estaInativo()) {
            return redirect(self::LOGIN);
        }

        if ($usuario->status === 'blocked' || $usuario->status === 'pending_confirmation') {
            return redirect(self::LOGIN);
        }

        if ($usuario->role === 'student') {
            return redirect('/aluno');
        }

        if ($usuario->role === 'instructor') {
            return redirect('/inst');
        }

        if ($usuario->role !== 'admin') {
            return redirect('/');
        }

        $admin = [
            'sub' => (string) $usuario->id,
            'name' => (string) $usuario->name,
            'role' => (string) $usuario->role,
        ];

        $request->attributes->set('auth_user', $admin);
        View::share('admin', $admin);

        return $next($request);
    }
}
