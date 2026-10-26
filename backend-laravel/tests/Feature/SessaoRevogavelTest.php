<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Jwt;
use Firebase\JWT\JWT as FirebaseJwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * ADR 022 — sessão que o servidor consegue encerrar, bloqueio por rede e cadastro que não
 * revela quem já existe.
 *
 * O JWT valia 12 h e nada o cancelava: "Sair" só apagava o cookie, e trocar a senha não
 * derrubava quem já tinha o token.
 */
final class SessaoRevogavelTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private const SENHA = 'senha123456';

    private function entrar(string $cpf, string $senha = self::SENHA, string $ip = '10.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', ['cpf' => $cpf, 'password' => $senha, 'role' => 'student']);
    }

    private function eu(string $token): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me');
    }

    /** @return array{id: string, cpf: string, token: string} */
    private function alunoComDuasSessoes(): array
    {
        $aluno = $this->makeStudent('Aluno Sessao');
        $a = $this->entrar($aluno['cpf'])->assertOk()->json('token');
        $b = $this->entrar($aluno['cpf'])->assertOk()->json('token');
        $this->assertNotSame($a, $b, 'cada login emite um token próprio (jti)');
        $this->flushHeaders();

        return ['id' => $aluno['id'], 'cpf' => $aluno['cpf'], 'token' => $a, 'outro' => $b];
    }

    // ---------- "Sair" ----------

    public function test_sair_cancela_o_token_no_servidor_e_nao_so_o_cookie(): void
    {
        $s = $this->alunoComDuasSessoes();
        $this->eu($s['token'])->assertOk();

        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer '.$s['token'])->postJson('/api/auth/logout')->assertOk();

        $this->flushHeaders();
        // O token que saiu morreu, mesmo apresentado de novo.
        $this->eu($s['token'])->assertStatus(401);
        $this->flushHeaders();
        // E o outro aparelho da mesma pessoa continua entrando.
        $this->eu($s['outro'])->assertOk();
    }

    public function test_sair_pela_tela_cancela_o_token(): void
    {
        $s = $this->alunoComDuasSessoes();

        $this->flushHeaders();
        $this->withUnencryptedCookie('ava_session', $s['token'])->post('/sair')->assertRedirect('/');

        $this->flushHeaders();
        $this->eu($s['token'])->assertStatus(401);
        $this->flushHeaders();
        $this->eu($s['outro'])->assertOk();
    }

    // ---------- trocar / redefinir senha ----------

    public function test_trocar_a_senha_derruba_as_outras_sessoes_e_mantem_a_atual(): void
    {
        $s = $this->alunoComDuasSessoes();

        $res = $this->withHeader('Authorization', 'Bearer '.$s['token'])
            ->putJson('/api/auth/password', ['currentPassword' => self::SENHA, 'newPassword' => 'NovaSenha#2026x'])
            ->assertOk();

        // A sessão que trocou a senha segue, com cookie novo.
        $cookie = $res->getCookie('ava_session', false);
        $this->assertNotNull($cookie);
        $this->flushHeaders();
        $this->eu((string) $cookie->getValue())->assertOk();

        // O token antigo dela e o do outro aparelho morreram.
        $this->flushHeaders();
        $this->eu($s['token'])->assertStatus(401);
        $this->flushHeaders();
        $this->eu($s['outro'])->assertStatus(401);
    }

    public function test_redefinicao_pela_coordenacao_derruba_as_sessoes_do_aluno(): void
    {
        $s = $this->alunoComDuasSessoes();
        $this->eu($s['token'])->assertOk();

        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken('admin'))
            ->putJson("/api/auth/users/{$s['id']}/password", ['newPassword' => 'NovaSenha#2026x'])
            ->assertOk();

        $this->flushHeaders();
        $this->eu($s['token'])->assertStatus(401);
        $this->flushHeaders();
        $this->eu($s['outro'])->assertStatus(401);
    }

    // ---------- bloquear ----------

    public function test_bloquear_derruba_a_sessao_e_ela_nao_volta_ao_reativar(): void
    {
        $s = $this->alunoComDuasSessoes();
        $admin = $this->staffToken('admin');

        $this->flushHeaders();
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson("/api/auth/users/{$s['id']}/status", ['status' => 'blocked'])->assertOk();
        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson("/api/auth/users/{$s['id']}/status", ['status' => 'active'])->assertOk();

        $this->flushHeaders();
        // Reativada, a conta entra de novo — mas só com login novo, não com o token de antes.
        $this->eu($s['token'])->assertStatus(401);
        $this->flushHeaders();
        $this->entrar($s['cpf'])->assertOk();
    }

    // ---------- compatibilidade ----------

    public function test_token_emitido_antes_da_mudanca_continua_valendo_ate_a_primeira_troca(): void
    {
        $aluno = $this->makeStudent('Aluno Antigo');

        // Token no formato antigo: sem `sv` e sem `jti`.
        $antigo = FirebaseJwt::encode(
            ['sub' => $aluno['id'], 'name' => $aluno['name'], 'role' => 'student', 'iat' => time(), 'exp' => time() + 3600],
            Jwt::secret(),
            'HS256',
        );

        $this->flushHeaders();
        $this->eu($antigo)->assertOk();

        $this->flushHeaders();
        $this->withHeader('Authorization', 'Bearer '.$antigo)
            ->putJson('/api/auth/password', ['currentPassword' => self::SENHA, 'newPassword' => 'NovaSenha#2026x'])->assertOk();

        $this->flushHeaders();
        $this->eu($antigo)->assertStatus(401);
    }

    // ---------- bloqueio por rede ----------

    public function test_quem_erra_a_senha_tranca_so_o_proprio_acesso_e_nao_a_conta(): void
    {
        $aluno = $this->makeStudent('Aluno Alvo');

        for ($i = 0; $i < 5; $i++) {
            $this->entrar($aluno['cpf'], 'errada', '10.0.0.66')->assertStatus(401);
        }
        // A rede de quem errou está trancada, mesmo com a senha certa...
        $this->entrar($aluno['cpf'], self::SENHA, '10.0.0.66')->assertStatus(429)->assertJsonPath('code', 'ACCOUNT_LOCKED');

        // ...e o aluno de verdade, em outra rede, entra normalmente.
        $this->entrar($aluno['cpf'], self::SENHA, '10.0.0.77')->assertOk();
        $this->assertNull(User::query()->whereKey($aluno['id'])->value('lockedUntil'));
    }

    public function test_cpf_que_nao_existe_tranca_igual_e_o_429_nao_denuncia_quem_tem_conta(): void
    {
        $inexistente = $this->makeCpf();

        for ($i = 0; $i < 5; $i++) {
            $this->entrar($inexistente, 'errada', '10.0.0.88')->assertStatus(401);
        }

        $this->entrar($inexistente, 'errada', '10.0.0.88')->assertStatus(429)->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    public function test_muitos_erros_somados_de_varias_redes_travam_a_conta_para_todos(): void
    {
        $aluno = $this->makeStudent('Aluno Forca Bruta');
        DB::table('User')->where('id', $aluno['id'])->update(['failedLoginAttempts' => 29]);

        $this->entrar($aluno['cpf'], 'errada', '10.0.1.1')->assertStatus(401);

        $this->assertNotNull(User::query()->whereKey($aluno['id'])->value('lockedUntil'));
        $this->entrar($aluno['cpf'], self::SENHA, '10.0.1.2')->assertStatus(429)->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    public function test_login_certo_zera_o_contador_da_rede(): void
    {
        $aluno = $this->makeStudent('Aluno Zera');

        for ($i = 0; $i < 4; $i++) {
            $this->entrar($aluno['cpf'], 'errada', '10.0.2.1')->assertStatus(401);
        }
        $this->entrar($aluno['cpf'], self::SENHA, '10.0.2.1')->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->entrar($aluno['cpf'], 'errada', '10.0.2.1')->assertStatus(401);
        }
        $this->entrar($aluno['cpf'], self::SENHA, '10.0.2.1')->assertOk();
    }

    // ---------- cadastro que não revela quem existe ----------

    /** @return array<string, mixed> */
    private function cadastro(string $cpf, string $email): array
    {
        return ['name' => 'Pessoa Nova '.uniqid(), 'email' => $email, 'cpf' => $cpf, 'password' => 'Senha#Forte2026'];
    }

    public function test_cadastro_publico_com_cpf_ou_email_existente_responde_igual_e_nao_grava(): void
    {
        $aluno = $this->makeStudent('Aluno Existente');
        $emailDoAluno = (string) User::query()->whereKey($aluno['id'])->value('email');
        $antes = User::query()->withTrashed()->count();

        $novo = $this->postJson('/api/auth/register', $this->cadastro($this->makeCpf(), 'novo-'.uniqid().'@example.com'))->assertStatus(201);
        $cpfRepetido = $this->postJson('/api/auth/register', $this->cadastro($aluno['cpf'], 'outro-'.uniqid().'@example.com'))->assertStatus(201);
        $emailRepetido = $this->postJson('/api/auth/register', $this->cadastro($this->makeCpf(), $emailDoAluno))->assertStatus(201);

        // Mesma forma de resposta nos três casos, sem token e sem a marca interna.
        foreach ([$novo, $cpfRepetido, $emailRepetido] as $r) {
            $this->assertNull($r->json('token'));
            $this->assertSame('pending_confirmation', $r->json('user.status'));
            $this->assertSame(['token', 'user'], array_keys($r->json()));
        }
        // Só o cadastro realmente novo gravou uma conta.
        $this->assertSame($antes + 1, User::query()->withTrashed()->count());
        // E a conta que já existia não foi tocada.
        $this->assertSame('active', User::query()->whereKey($aluno['id'])->value('status'));
    }

    public function test_cadastro_pela_tela_com_dado_existente_mostra_a_mesma_mensagem(): void
    {
        $aluno = $this->makeStudent('Aluno Tela');
        $antes = User::query()->withTrashed()->count();

        $this->post('/cadastro', $this->cadastro($aluno['cpf'], 'tela-'.uniqid().'@example.com'))
            ->assertRedirect('/entrar')
            ->assertSessionHas('aviso')
            ->assertSessionHasNoErrors();

        $this->assertSame($antes, User::query()->withTrashed()->count());
    }

    public function test_admin_ao_cadastrar_continua_vendo_o_conflito(): void
    {
        $aluno = $this->makeStudent('Aluno Conflito');
        $this->flushHeaders();

        $this->withHeader('Authorization', 'Bearer '.$this->staffToken('admin'))
            ->postJson('/api/auth/register', $this->cadastro($aluno['cpf'], 'adm-'.uniqid().'@example.com') + ['role' => 'student'])
            ->assertStatus(409)->assertJsonPath('code', 'CONFLICT');
    }
}
