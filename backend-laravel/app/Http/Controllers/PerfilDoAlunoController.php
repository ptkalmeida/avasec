<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Rules\StrongPasswordRule;
use App\Services\AuditLogger;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil do aluno, em Blade.
 *
 * ## O que veio do React, e o que ficou para trás
 *
 * O `ProfileView.tsx` tinha duas naturezas de coisa misturadas. Vieram as que
 * existem no SERVIDOR: o nome (com a senha atual como confirmação) e a troca
 * de senha. Não vieram as que só existiam no navegador de quem as via:
 *
 * - avatar, foto, câmera e "frase de status", guardados em `localStorage` —
 *   noutro computador, nada disso existia;
 * - um "e-mail" que não era o e-mail da conta, e sim `nome.sobrenome@lms.edu`
 *   montado a partir do nome;
 * - o selo Online/Offline, que já saiu da aula em 13/10 pelo mesmo motivo:
 *   verde para todo mundo, sempre;
 * - um botão que limpava o `localStorage` inteiro ("restaurar simulador");
 * - uma "validação de documento" antes da troca de senha que só contava os
 *   dígitos digitados — qualquer sequência de onze números passava;
 * - o "Histórico Escolar Oficial", com assinaturas de duas pessoas escritas
 *   no código. Ver a nota na view.
 *
 * As preferências de acessibilidade também estavam aqui, e ficaram para
 * depois por decisão do responsável (20/10/2026), junto com o painel.
 *
 * Nenhuma regra de negócio mora aqui: nome e senha passam pelo mesmo
 * `AuthService` que a API usa, com a mesma auditoria.
 */
final class PerfilDoAlunoController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    public function mostrar(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $conta = User::query()->find($aluno['sub'], ['id', 'name', 'nomeSocial', 'email', 'cpf']);
        abort_if($conta === null, 404);

        return view('aluno.perfil', [
            'conta' => [
                'nome' => (string) $conta->name,
                'nomeSocial' => is_string($conta->nomeSocial) && trim($conta->nomeSocial) !== '' ? $conta->nomeSocial : null,
                'email' => is_string($conta->email) ? $conta->email : null,
                'cpf' => self::cpfMascarado(is_string($conta->cpf) ? $conta->cpf : null),
            ],
            'regraDaSenha' => 'Pelo menos '.StrongPasswordRule::MIN_LENGTH.' caracteres, com ao menos uma letra e um número.',
        ]);
    }

    /**
     * Trocar o nome exige a senha atual.
     *
     * O nome não é só exibição: é o que sai no próximo certificado emitido, e
     * o `renameUser` reescreve o nome nas matrículas e entregas. Uma sessão
     * esquecida aberta num laboratório não pode bastar para isso.
     */
    public function alterarNome(Request $request): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $dados = $request->validateWithBag('nome', [
            'nome' => ['required', 'string', 'min:2', 'max:150'],
            'senhaAtual' => ['required', 'string', 'max:128'],
        ]);

        if (! $this->auth->senhaConfere($aluno['sub'], (string) $dados['senhaAtual'])) {
            return back()
                ->withInput($request->except('senhaAtual'))
                ->withErrors(['senhaAtual' => 'A senha atual não confere.'], 'nome');
        }

        $novo = trim((string) $dados['nome']);
        $this->auth->renameUser($aluno['sub'], $novo, $aluno);
        $this->audit->log($request, 'Alteração de Nome', "Usuário {$aluno['sub']} renomeado para \"{$novo}\".");

        return redirect('/aluno/perfil')->with('perfilAviso', 'Nome atualizado.');
    }

    public function alterarSenha(Request $request): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $dados = $request->validateWithBag('senha', [
            'senhaAtual' => ['required', 'string', 'max:128'],
            'novaSenha' => ['required', 'string', 'max:128', new StrongPasswordRule, 'confirmed'],
        ], [
            'novaSenha.confirmed' => 'A confirmação não é igual à nova senha.',
        ]);

        try {
            // A senha atual é conferida DENTRO do service, como na API: sem
            // ela, uma sessão sequestrada trocaria a senha e tomaria a conta.
            $this->auth->changePassword($aluno['sub'], (string) $dados['novaSenha'], (string) $dados['senhaAtual']);
        } catch (ApiException $erro) {
            return back()->withErrors(['senhaAtual' => $erro->getMessage()], 'senha');
        }

        $this->audit->log($request, 'Alteração de Senha', 'Senha alterada pelo próprio usuário.');

        return redirect('/aluno/perfil')->with('perfilAviso', 'Senha alterada. Use a nova senha no próximo acesso.');
    }

    /**
     * "759.***.***-23": o bastante para a pessoa reconhecer o próprio CPF, e
     * pouco para quem olha a tela por cima do ombro num laboratório.
     */
    private static function cpfMascarado(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf);

        if (! is_string($digitos) || strlen($digitos) !== 11) {
            return null;
        }

        return substr($digitos, 0, 3).'.***.***-'.substr($digitos, 9, 2);
    }
}
