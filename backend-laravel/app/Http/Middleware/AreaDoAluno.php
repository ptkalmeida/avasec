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
 * Quem está vendo a tela do aluno — para rotas **web**, não para a API.
 *
 * ## O que este middleware NÃO faz
 *
 * Não muda o fluxo de autenticação. Ele não emite token, não cria sessão do
 * Laravel, não toca no login nem no logout. Ele **lê o mesmo cookie
 * `ava_session` que já existe**, com a mesma função de decodificação do
 * `JwtAuthenticate`, e reconfere a conta no banco com a mesma regra do
 * `RequireActiveAccount`.
 *
 * A troca do JWT pela sessão do Laravel é item da M2 no plano 16 e exige
 * validação humana explícita (AGENTS.md). Escolher o caminho mínimo aqui é o
 * que permite entregar as telas do aluno sem antecipar aquela decisão — e, no
 * dia em que ela for tomada, o que muda é este arquivo, e só ele.
 *
 * ## Por que não reaproveitar os middlewares da API diretamente
 *
 * Porque a resposta é outra. `JwtAuthenticate` e `RequireActiveAccount` lançam
 * `ApiException`, que vira JSON sob `/api/*`. Numa tela, visitante sem token
 * não deve receber JSON nem a página de erro do framework: deve ser levado ao
 * login. A verificação é a mesma; o que muda é o que se faz quando ela falha.
 *
 * ## Onde o login está
 *
 * Em `/entrar`, como tela de verdade (fase M2). Era um modal dentro do SPA, e
 * é por isso que este middleware nasceu mandando para `/?janela=login`: não
 * havia endereço para onde mandar. Ter um é o que permite a uma rota protegida
 * devolver a pessoa ao lugar certo.
 */
final class AreaDoAluno
{
    /** Para onde vai quem não pode ver a tela. */
    private const LOGIN = '/entrar';

    public function handle(Request $request, Closure $next): Response
    {
        $token = JwtAuthenticate::extractToken($request);

        try {
            $identidade = JwtAuthenticate::decode($token);
        } catch (Throwable) {
            // Token expirado ou adulterado tem o mesmo destino que token ausente:
            // a tela de entrada. Distinguir os dois aqui só serviria para contar
            // a quem tentou qual dos dois casos ele acertou.
            return redirect(self::LOGIN);
        }

        if ($identidade === null) {
            return redirect(self::LOGIN);
        }

        /*
         * `withTrashed` de propósito, pela ADR 12: a conta inativada some das
         * consultas padrão, e sem isto ela cairia no ramo "não existe".
         * O status vem SEMPRE do banco — nunca do token, que vale 12h e pode
         * estar descrevendo uma conta que já mudou.
         */
        $usuario = Usuario::query()->withTrashed()->find($identidade['sub'], ['id', 'nome', 'papel', 'status', 'inativado_em']);

        if ($usuario === null || $usuario->estaInativo()) {
            return redirect(self::LOGIN);
        }

        if ($usuario->status === 'blocked' || $usuario->status === 'pending_confirmation') {
            return redirect(self::LOGIN);
        }

        /*
         * Papel lido do banco, não do token: um usuário rebaixado não pode
         * entrar com um token antigo. Gestor e admin não são barrados por erro —
         * eles têm painel próprio, que ainda é React; mandá-los para a raiz os
         * leva ao painel deles em vez de a uma área que não é a sua.
         */
        if ($usuario->papel !== 'student') {
            return redirect('/');
        }

        $aluno = ['sub' => (string) $usuario->id, 'nome' => (string) $usuario->nome, 'papel' => 'student'];

        // Nos atributos para os controllers (é o formato que os services pedem)
        // e na view para o layout, que precisa do nome na saudação e no menu.
        $request->attributes->set('auth_user', $aluno);
        View::share('aluno', $aluno);

        return $next($request);
    }
}
