<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\GeneratesCpf;
use Tests\TestCase;

/**
 * Redefinição de senha pela coordenação (PUT /api/autenticacao/usuarios/{id}/senha).
 *
 * A tela de gestão oferecia "Redefinir Senha" sem endpoint algum: só alterava estado
 * local do React, então a coordenação via a confirmação e a senha antiga continuava
 * valendo. Estes testes fixam as duas metades do contrato — a senha realmente muda,
 * e só admin consegue mudá-la.
 */
final class AdminResetPasswordTest extends TestCase
{
    use DatabaseTransactions;
    use GeneratesCpf;

    protected function setUp(): void
    {
        parent::setUp();
        // Vários logins em sequência estouram o limitador de auth; o alvo aqui é
        // autorização e efeito, não rate limit (coberto em RateLimitKeyTest).
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function tokenForRole(string $role): string
    {
        $user = DB::table('usuario')->where('papel', $role)->where('status', 'active')->first(['id', 'nome', 'papel']);
        $this->assertNotNull($user, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($user->id, $user->nome, $user->papel);
    }

    /** @return array{id: string, email: string} */
    private function createStudent(): array
    {
        $email = 'reset-'.uniqid().'@example.com';
        $response = $this->postJson('/api/autenticacao/cadastro', [
            'nome' => 'Aluno Reset '.uniqid(),
            'email' => $email,
            'password' => 'senhaAntiga1',
            'cpf' => $this->makeCpf(),
        ]);
        $response->assertStatus(201);

        $id = $response->json('usuario.id');
        $this->assertIsString($id);

        // Conta nasce pendente de homologação; ativa para poder testar o login.
        DB::table('usuario')->where('id', $id)->update(['status' => 'active']);

        return ['id' => $id, 'email' => $email];
    }

    public function test_admin_reset_replaces_password_and_old_one_stops_working(): void
    {
        $aluno = $this->createStudent();

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => 'senhaNova123'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->postJson('/api/autenticacao/entrar', ['email' => $aluno['email'], 'password' => 'senhaNova123'])
            ->assertOk();

        // A metade que importa: redefinir REVOGA a anterior. Sem o endpoint, este
        // caso passava a impressão de sucesso e a senha velha continuava válida.
        $this->postJson('/api/autenticacao/entrar', ['email' => $aluno['email'], 'password' => 'senhaAntiga1'])
            ->assertStatus(401);
    }

    public function test_reset_does_not_require_current_password(): void
    {
        $aluno = $this->createStudent();

        // O admin não conhece a senha do aluno — exigi-la tornaria o fluxo impossível.
        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => 'outraSenha99'])
            ->assertOk();
    }

    public function test_weak_password_is_rejected(): void
    {
        $aluno = $this->createStudent();
        $token = $this->tokenForRole('admin');

        // O PIN de 4 dígitos do MVP não passa mais (ADR 11): a política é a mesma
        // do autosserviço, sem exceção para redefinição administrativa.
        $this->withToken($token)
            ->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => '5678'])
            ->assertStatus(400);

        $this->withToken($token)
            ->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => 'somenteletras'])
            ->assertStatus(400);

        // A senha original segue valendo: validação recusada não altera nada.
        $this->postJson('/api/autenticacao/entrar', ['email' => $aluno['email'], 'password' => 'senhaAntiga1'])
            ->assertOk();
    }

    public function test_instructor_cannot_reset_password_of_a_student(): void
    {
        $aluno = $this->createStudent();

        $this->withToken($this->tokenForRole('instructor'))
            ->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => 'invasao1234'])
            ->assertStatus(403);

        $this->postJson('/api/autenticacao/entrar', ['email' => $aluno['email'], 'password' => 'senhaAntiga1'])
            ->assertOk();
    }

    public function test_student_cannot_reset_password_of_another_student(): void
    {
        $alvo = $this->createStudent();
        $outro = $this->createStudent();
        $tokenOutro = Jwt::issue($outro['id'], 'Aluno Reset', 'student');

        $this->withToken($tokenOutro)
            ->putJson("/api/autenticacao/usuarios/{$alvo['id']}/senha", ['newPassword' => 'invasao1234'])
            ->assertStatus(403);

        $this->postJson('/api/autenticacao/entrar', ['email' => $alvo['email'], 'password' => 'senhaAntiga1'])
            ->assertOk();
    }

    public function test_anonymous_cannot_reset_password(): void
    {
        $aluno = $this->createStudent();

        $this->putJson("/api/autenticacao/usuarios/{$aluno['id']}/senha", ['newPassword' => 'invasao1234'])
            ->assertStatus(401);
    }

    public function test_reset_on_unknown_user_is_404(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->putJson('/api/autenticacao/usuarios/id-que-nao-existe/senha', ['newPassword' => 'senhaNova123'])
            ->assertStatus(404);
    }
}
