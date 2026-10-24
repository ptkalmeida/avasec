<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Rules\StrongPasswordRule;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Support\SessaoJwt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Meu perfil" e "Trocar senha" do administrador — os dois itens do menu do
 * usuário que o Anexo D (§1.4.2) exige em toda topbar do back-office.
 *
 * ## É funcionalidade nova, e toca autenticação
 *
 * O painel React não tinha nenhum dos dois. A troca de senha segue, sem
 * variação, a regra que o aluno já usa em `/aluno/perfil/senha`: a senha atual
 * é conferida DENTRO do `AuthService` (sem ela, uma sessão esquecida aberta
 * tomaria a conta), a nova passa pela `StrongPasswordRule`, o limitador é o
 * `auth-password` (5 por 15 min por usuário) e a troca fica na auditoria. Errar
 * a senha atual NÃO conta como tentativa de login, então não bloqueia a conta.
 *
 * Nenhuma regra de negócio mora aqui: tudo passa pelo mesmo service da API.
 */
final class PerfilDoAdminController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    public function perfil(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $conta = User::query()->find($admin['sub'], ['id', 'name', 'email']);
        abort_if($conta === null, 404);

        return view('admin.perfil', [
            'conta' => [
                'nome' => (string) $conta->name,
                'email' => is_string($conta->email) ? $conta->email : null,
            ],
        ]);
    }

    public function formularioDeSenha(): View
    {
        return view('admin.senha', [
            'regraDaSenha' => 'Pelo menos '.StrongPasswordRule::MIN_LENGTH.' caracteres, com ao menos uma letra e um número.',
        ]);
    }

    public function alterarSenha(Request $request): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $dados = $request->validateWithBag('senha', [
            'senhaAtual' => ['required', 'string', 'max:128'],
            'novaSenha' => ['required', 'string', 'max:128', new StrongPasswordRule, 'confirmed'],
        ], [
            'novaSenha.confirmed' => 'A confirmação não é igual à nova senha.',
        ]);

        try {
            $novoToken = $this->auth->changePassword($admin['sub'], (string) $dados['novaSenha'], (string) $dados['senhaAtual']);
        } catch (ApiException $erro) {
            return back()->withErrors(['senhaAtual' => $erro->getMessage()], 'senha');
        }

        $this->audit->log($request, 'Alteração de Senha', 'Senha alterada pelo próprio usuário.');

        // As outras sessões da conta foram encerradas; esta segue, com token novo (ADR 022).
        return redirect('/admin/perfil/senha')
            ->with('senhaAviso', 'Senha alterada. Use a nova senha no próximo acesso.')
            ->withCookie(SessaoJwt::cookie($novoToken));
    }
}
