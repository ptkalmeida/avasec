<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Quem está vendo a tela do instrutor — para rotas **web**, não para a API.
 *
 * Irmão do `AreaDoAluno`, e pelas mesmas razões: lê o cookie `ava_session` que
 * já existe, reconfere a conta no banco (nunca no token, que vale 12 h e pode
 * estar descrevendo uma conta que mudou) e manda ao login quem não pode entrar.
 * A troca do JWT pela sessão do Laravel continua sendo decisão de outra fase.
 *
 * ## O admin entra, e isso é diferente do aluno
 *
 * No `AreaDoAluno`, quem não é aluno vai para a raiz: gestor e admin têm painel
 * próprio. Aqui o admin **passa**, porque a API inteira do instrutor já o
 * aceita (`role:instructor,admin`) e porque o admin é quem socorre um curso
 * quando o instrutor não está. O que ele vê continua sendo decidido pelos
 * services, que para o admin não restringem por posse.
 *
 * O papel efetivo vai nos atributos: um controller que precise distinguir
 * "meus cursos" de "todos" lê `role`, e não o endereço.
 */
final class AreaDoInstrutor
{
    /** Para onde vai quem não pode ver a tela. */
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
        $usuario = Usuario::query()->withTrashed()->find($identidade['sub'], ['id', 'nome', 'papel', 'status', 'inativado_em']);

        if ($usuario === null || $usuario->estaInativo()) {
            return redirect(self::LOGIN);
        }

        if ($usuario->status === 'blocked' || $usuario->status === 'pending_confirmation') {
            return redirect(self::LOGIN);
        }

        /*
         * Papel do BANCO. Um instrutor rebaixado a aluno não entra com o token
         * antigo; e o aluno que digitar `/inst` vai para a área dele, que é
         * onde ele tem o que fazer — não para uma página de erro.
         */
        if ($usuario->papel === 'student') {
            return redirect('/aluno');
        }

        if ($usuario->papel !== 'instructor' && $usuario->papel !== 'admin') {
            return redirect('/');
        }

        $instrutor = [
            'sub' => (string) $usuario->id,
            'nome' => (string) $usuario->nome,
            'papel' => (string) $usuario->papel,
        ];

        $request->attributes->set('auth_user', $instrutor);
        View::share('instrutor', $instrutor);

        return $next($request);
    }
}
