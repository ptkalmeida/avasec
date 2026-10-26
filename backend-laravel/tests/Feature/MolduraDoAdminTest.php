<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * A moldura do painel administrativo (Anexo D §1) e os dois itens do menu do
 * usuário que o painel React não tinha: "Meu perfil" e "Trocar senha".
 *
 * Trocar senha toca autenticação, então o que mais pesa aqui é: exige a senha
 * atual, errar não bloqueia a conta, a senha nova passa pela regra de força, e
 * só o admin (com conta ativa, papel do BANCO) chega à tela.
 */
final class MolduraDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private const SENHA = 'senha123456';

    protected function setUp(): void
    {
        parent::setUp();
        // O limitador é coberto à parte, no teste da API; aqui ele dispararia
        // ao longo do arquivo.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function conta(string $role, string $nome): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash(self::SENHA, PASSWORD_BCRYPT),
            'role' => $role,
        ]);

        return ['id' => $id, 'name' => $nome, 'token' => Jwt::issue($id, $nome, $role)];
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/admin/perfil')->assertRedirect('/entrar');
        $this->get('/admin/perfil/senha')->assertRedirect('/entrar');
        $this->post('/admin/perfil/senha', [])->assertRedirect('/entrar');
    }

    public function test_aluno_e_instrutor_vao_para_a_area_deles(): void
    {
        $aluno = $this->makeStudent('Aluno Fora do Admin');
        $instrutor = $this->conta('instructor', 'Instrutor Fora do Admin');

        $this->como($aluno['token'])->get('/admin/perfil')->assertRedirect('/aluno');
        $this->como($instrutor['token'])->get('/admin/perfil/senha')->assertRedirect('/inst');
    }

    public function test_conta_inativada_ou_rebaixada_nao_entra_com_o_token_antigo(): void
    {
        $inativa = $this->conta('admin', 'Admin Inativado');
        User::query()->findOrFail($inativa['id'])->inativar($inativa['id'], 'teste');

        $rebaixada = $this->conta('admin', 'Admin Rebaixado');
        User::query()->whereKey($rebaixada['id'])->update(['role' => 'student']);

        $this->como($inativa['token'])->get('/admin/perfil')->assertRedirect('/entrar');
        $this->como($rebaixada['token'])->get('/admin/perfil')->assertRedirect('/aluno');
    }

    public function test_a_moldura_traz_sidebar_topbar_e_menu_do_usuario(): void
    {
        $admin = $this->conta('admin', 'Marina Costa Admin');

        $resposta = $this->como($admin['token'])->get('/admin/perfil');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        // Sidebar: navegação com a seção principal e os grupos.
        $resposta->assertSee('Navegação do painel administrativo');
        $resposta->assertSee('Dashboard &amp; Relatórios', false);
        $resposta->assertSee('Equipe Pedagógica');
        $resposta->assertSee('Alunos');
        // Topbar: nome e perfil visíveis, e os três itens obrigatórios do menu.
        $resposta->assertSee('Marina Costa Admin');
        $resposta->assertSee('Administrador');
        $resposta->assertSee('Meu perfil');
        $resposta->assertSee('Trocar senha');
        $resposta->assertSee('Sair');
        $resposta->assertSee('action="/sair"', false);
        // Off-canvas: botão de menu para telas estreitas.
        $resposta->assertSee('Abrir o menu');
        // Ícones: FontAwesome, e nada de emoji ou outro set.
        $resposta->assertSee('fa-solid', false);
    }

    public function test_item_de_flag_desligada_some_do_menu(): void
    {
        $admin = $this->conta('admin', 'Admin Flags');

        config(['features.dadosGerenciais' => false, 'features.gestaoConteudoSite' => false]);
        $desligado = $this->como($admin['token'])->get('/admin/perfil');
        $desligado->assertDontSee('Dados Gerenciais');
        $desligado->assertDontSee('Páginas do Site');
        // Sem flag: sempre presentes.
        $desligado->assertSee('Equipe Pedagógica');

        config(['features.dadosGerenciais' => true, 'features.gestaoConteudoSite' => true]);
        $ligado = $this->como($admin['token'])->get('/admin/perfil');
        $ligado->assertSee('Dados Gerenciais');
        $ligado->assertSee('Páginas do Site');
    }

    public function test_perfil_mostra_nome_e_email_da_conta(): void
    {
        $admin = $this->conta('admin', 'Admin Dados Conta');
        $email = (string) User::query()->whereKey($admin['id'])->value('email');

        $resposta = $this->como($admin['token'])->get('/admin/perfil');

        $resposta->assertSee('Admin Dados Conta');
        $resposta->assertSee($email);
    }

    public function test_troca_a_senha_com_a_senha_atual_certa(): void
    {
        $admin = $this->conta('admin', 'Admin Troca Senha');

        $resposta = $this->como($admin['token'])->post('/admin/perfil/senha', [
            'senhaAtual' => self::SENHA,
            'novaSenha' => 'novaSenha2026',
            'novaSenha_confirmation' => 'novaSenha2026',
        ]);

        $resposta->assertRedirect('/admin/perfil/senha');
        $resposta->assertSessionHas('senhaAviso');

        $hash = (string) User::query()->whereKey($admin['id'])->value('passwordHash');
        $this->assertTrue(password_verify('novaSenha2026', $hash));
        $this->assertFalse(password_verify(self::SENHA, $hash));

        // Fica na auditoria.
        $this->assertTrue(
            DB::table('SecurityLog')->where('user', $admin['name'])->where('action', 'Alteração de Senha')->exists(),
        );
    }

    public function test_senha_atual_errada_nao_troca_e_nao_bloqueia_a_conta(): void
    {
        $admin = $this->conta('admin', 'Admin Senha Errada');
        $antes = (string) User::query()->whereKey($admin['id'])->value('passwordHash');

        for ($i = 0; $i < 6; $i++) {
            $this->como($admin['token'])->from('/admin/perfil/senha')->post('/admin/perfil/senha', [
                'senhaAtual' => 'errada-errada',
                'novaSenha' => 'novaSenha2026',
                'novaSenha_confirmation' => 'novaSenha2026',
            ])->assertRedirect('/admin/perfil/senha')->assertSessionHasErrors('senhaAtual', errorBag: 'senha');
        }

        $conta = User::query()->findOrFail($admin['id']);
        $this->assertSame($antes, (string) $conta->passwordHash);
        $this->assertSame(0, (int) $conta->failedLoginAttempts);
        $this->assertNull($conta->lockedUntil);
        $this->como($admin['token'])->get('/admin/perfil')->assertOk();
    }

    public function test_senha_nova_fraca_ou_sem_confirmacao_e_recusada(): void
    {
        $admin = $this->conta('admin', 'Admin Senha Fraca');
        $antes = (string) User::query()->whereKey($admin['id'])->value('passwordHash');

        $this->como($admin['token'])->from('/admin/perfil/senha')->post('/admin/perfil/senha', [
            'senhaAtual' => self::SENHA,
            'novaSenha' => '12345678',
            'novaSenha_confirmation' => '12345678',
        ])->assertSessionHasErrors('novaSenha', errorBag: 'senha');

        $this->como($admin['token'])->from('/admin/perfil/senha')->post('/admin/perfil/senha', [
            'senhaAtual' => self::SENHA,
            'novaSenha' => 'novaSenha2026',
            'novaSenha_confirmation' => 'outraSenha2026',
        ])->assertSessionHasErrors('novaSenha', errorBag: 'senha');

        $this->assertSame($antes, (string) User::query()->whereKey($admin['id'])->value('passwordHash'));
    }

    public function test_o_formulario_de_senha_pede_as_tres_senhas(): void
    {
        $admin = $this->conta('admin', 'Admin Formulario');

        $resposta = $this->como($admin['token'])->get('/admin/perfil/senha');

        $resposta->assertOk();
        $resposta->assertSee('name="senhaAtual"', false);
        $resposta->assertSee('name="novaSenha"', false);
        $resposta->assertSee('name="novaSenha_confirmation"', false);
        $resposta->assertSee('autocomplete="new-password"', false);
    }
}
