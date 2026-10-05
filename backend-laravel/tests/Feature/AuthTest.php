<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\GeneratesCpf;
use Tests\TestCase;

/**
 * Critérios de aceitação da Etapa 2 (autenticação) — reproduz, no Laravel, o que
 * tests/security.test.ts garante no Node. Requer MySQL de dev de pé e populado
 * (npm run db:up / db:seed). Escritas rodam em transação revertida.
 */
final class AuthTest extends TestCase
{
    use DatabaseTransactions;
    use GeneratesCpf;

    private function tokenForRole(string $role): string
    {
        $user = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($user, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($user->id, $user->name, $user->role);
    }

    private function uniqueEmail(string $prefix): string
    {
        return $prefix.'-'.uniqid().'@example.com';
    }

    /**
     * Cria uma conta ativa com senha conhecida, só para este teste.
     *
     * Antes estes casos faziam login com `'Admin Superior' / '9999'` — a senha de
     * demonstração da conta REAL do banco de desenvolvimento. Dois problemas: o
     * teste passava a depender do estado do ambiente (e caiu no dia em que a
     * senha foi rotacionada, que é justamente o que a auditoria pedia), e
     * corrigi-lo trocando pela senha nova gravaria uma credencial de verdade no
     * repositório. A conta nasce aqui e a transação a desfaz no fim.
     *
     * @return array{email: string, password: string}
     */
    private function contaComSenhaConhecida(string $role = 'admin'): array
    {
        $email = $this->uniqueEmail('login-'.$role);
        $senha = 'Teste'.uniqid().'9';

        DB::table('User')->insert([
            'id' => 'user-teste-'.uniqid(),
            'name' => 'Conta de Teste '.uniqid(),
            'email' => $email,
            'passwordHash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]),
            'role' => $role,
            'status' => 'active',
            // Colunas de ARMAZENAMENTO: em UTC, o relógio do banco. Não passam
            // por `Fuso`, que é só para o que uma pessoa lê.
            'createdAt' => CarbonImmutable::now()->toDateTimeString(),
            'updatedAt' => CarbonImmutable::now()->toDateTimeString(),
        ]);

        return ['email' => $email, 'password' => $senha];
    }

    // ---------- Autenticação e status de conta ----------

    public function test_login_with_correct_password_returns_token_and_sets_cookie(): void
    {
        $conta = $this->contaComSenhaConhecida('admin');
        $response = $this->postJson('/api/auth/login', $conta);

        $response->assertOk()->assertJsonPath('user.role', 'admin');
        $this->assertNotEmpty($response->json('token'));
        $response->assertCookie('ava_session');
    }

    public function test_login_with_wrong_password_returns_generic_401(): void
    {
        // Conta que EXISTE com senha errada. Contra uma conta inexistente o 401
        // viria de graça, e o teste passaria sem exercitar a comparação de senha.
        $conta = $this->contaComSenhaConhecida('admin');
        $response = $this->postJson('/api/auth/login', [
            'email' => $conta['email'],
            'password' => 'senha-errada-unica',
        ]);

        $response->assertStatus(401)->assertJsonPath('message', 'Usuário ou senha inválidos.');
    }

    public function test_protected_route_without_token_is_401(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson(['error' => true, 'code' => 'UNAUTHORIZED']);
    }

    public function test_public_registration_is_pending_and_gets_no_token(): void
    {
        $email = $this->uniqueEmail('pendente');
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Aluno Pendente Teste',
            'email' => $email,
            'password' => 'senha123456',
            'cpf' => $this->makeCpf(),
        ]);

        $register->assertStatus(201)
            ->assertJsonPath('user.status', 'pending_confirmation')
            ->assertJsonPath('token', null);

        // Login com a senha correta de conta pendente: 403 institucional, sem token.
        $login = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha123456']);
        $login->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_PENDING_CONFIRMATION');
        $this->assertNull($login->json('token'));
    }

    public function test_public_registration_cannot_self_promote_to_admin(): void
    {
        // O CPF vai preenchido DE PROPÓSITO: sem ele a requisição morreria em
        // 400 na validação e o teste deixaria de provar o que importa — que é a
        // regra de papel (e não a de campo obrigatório) que barra a escalada.
        $this->postJson('/api/auth/register', [
            'name' => 'Tentativa Escalada',
            'email' => $this->uniqueEmail('escalada'),
            'password' => 'senha123456',
            'cpf' => $this->makeCpf(),
            'role' => 'admin',
        ])->assertStatus(403);
    }

    public function test_admin_provisioned_account_is_active_but_admin_gets_no_third_party_token(): void
    {
        $adminToken = $this->tokenForRole('admin');
        $email = $this->uniqueEmail('provisionado');

        $register = $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/auth/register', [
            'name' => 'Instrutor Provisionado '.uniqid(),
            'email' => $email,
            'password' => 'senha123456',
            'role' => 'instructor',
        ]);

        $register->assertStatus(201)
            ->assertJsonPath('user.status', 'active')
            ->assertJsonPath('user.role', 'instructor')
            ->assertJsonPath('token', null);
    }

    public function test_blocked_account_gets_no_token_and_old_token_is_rejected(): void
    {
        $adminToken = $this->tokenForRole('admin');
        $email = $this->uniqueEmail('bloqueado');

        $register = $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/auth/register', [
            'name' => 'Aluno Bloqueado Teste '.uniqid(),
            'email' => $email,
            'password' => 'senha123456',
            'role' => 'student',
            'cpf' => $this->makeCpf(),
        ]);
        $userId = $register->json('user.id');

        // Token legítimo obtido enquanto ativo.
        $activeLogin = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha123456']);
        $activeLogin->assertOk();
        $oldToken = $activeLogin->json('token');

        // Admin bloqueia a conta.
        $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/auth/users/{$userId}/status", ['status' => 'blocked'])
            ->assertOk();

        // Novo login: 403, sem token.
        $blockedLogin = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha123456']);
        $blockedLogin->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $this->assertNull($blockedLogin->json('token'));

        // Token antigo é barrado pelo requireActiveAccount (rota que exige conta ativa).
        $this->withHeader('Authorization', "Bearer $oldToken")->getJson('/api/auth/users')
            ->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        // Desliga o rate limiter por IP para exercitar SÓ a lógica de lockout por conta
        // (5 tentativas) sem esbarrar no limite de 10/15min do throttle.
        $this->withoutMiddleware(ThrottleRequests::class);

        $adminToken = $this->tokenForRole('admin');
        $email = $this->uniqueEmail('lockout');
        $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/auth/register', [
            'name' => 'Aluno Lockout '.uniqid(),
            'email' => $email,
            'password' => 'senha123456',
            'role' => 'student',
            'cpf' => $this->makeCpf(),
        ])->assertStatus(201);

        // 5 tentativas erradas: as 4 primeiras retornam 401 genérico; a 5ª ainda 401,
        // mas dispara o bloqueio.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'errada'])
                ->assertStatus(401);
        }

        // Agora, mesmo com a senha CORRETA, a conta está bloqueada por tempo: 429 ACCOUNT_LOCKED.
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha123456'])
            ->assertStatus(429)->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    // ---------- Controle de acesso por perfil ----------

    public function test_student_cannot_list_other_students(): void
    {
        $token = $this->tokenForRole('student');
        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/auth/users?role=student')
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_change_account_status(): void
    {
        $token = $this->tokenForRole('instructor');
        $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/auth/users/any-id/status', ['status' => 'blocked'])
            ->assertStatus(403);
    }

    // ---------- Validação de entrada ----------

    public function test_register_rejects_invalid_email(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Teste', 'email' => 'nao-e-email', 'password' => 'senha123456'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_register_rejects_short_password(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Teste', 'email' => $this->uniqueEmail('curta'), 'password' => '123'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    // ---------- Sessão / me / logout ----------

    public function test_me_returns_current_user_with_cookie_session(): void
    {
        $login = $this->postJson('/api/auth/login', $this->contaComSenhaConhecida('admin'));
        $token = $login->json('token');

        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('role', 'admin');
    }

    public function test_logout_clears_session_cookie(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertOk()->assertJsonPath('success', true);
        // O cookie de sessão é expirado (forget => expiração no passado).
        $response->assertCookieExpired('ava_session');
    }
}
