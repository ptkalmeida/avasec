<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O cartão de "Gestão" da tela de login autenticou um ALUNO.
 *
 * O login do cartão é por nome, e nome não é único: com um aluno e um gestor
 * homônimos, `first()` pegava qualquer um dos dois. O cartão agora manda o
 * papel, e só uma conta desse papel entra. Requer MySQL de dev de pé.
 */
final class LoginPorPapelTest extends TestCase
{
    use DatabaseTransactions;

    private string $nome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nome = 'Homonimo '.uniqid();
    }

    private function conta(string $role, string $senha): string
    {
        $id = 'user-teste-'.uniqid();
        DB::table('User')->insert([
            'id' => $id,
            'name' => $this->nome,
            'email' => "{$role}-".uniqid().'@example.com',
            'passwordHash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]),
            'role' => $role,
            'status' => 'active',
            'createdAt' => CarbonImmutable::now()->toDateTimeString(),
            'updatedAt' => CarbonImmutable::now()->toDateTimeString(),
        ]);

        return $id;
    }

    public function test_cartao_de_gestao_com_a_senha_do_aluno_homonimo_nao_entra(): void
    {
        // O aluno é criado PRIMEIRO: é ele que o `first()` sem papel devolvia.
        $alunoId = $this->conta('student', 'SenhaAluno1');
        $this->conta('instructor', 'SenhaGestor1');

        $res = $this->postJson('/api/auth/login', [
            'name' => $this->nome, 'password' => 'SenhaAluno1', 'role' => 'instructor',
        ]);

        $res->assertStatus(401)->assertJsonPath('message', 'Usuário ou senha inválidos.');
        $res->assertCookieMissing('ava_session');
        // E não conta tentativa contra a conta do aluno, que não foi a pedida.
        $this->assertSame(0, (int) DB::table('User')->where('id', $alunoId)->value('failedLoginAttempts'));
    }

    public function test_cartao_de_gestao_entra_no_gestor_mesmo_com_aluno_homonimo_antes(): void
    {
        $this->conta('student', 'SenhaAluno1');
        $gestorId = $this->conta('instructor', 'SenhaGestor1');

        $res = $this->postJson('/api/auth/login', [
            'name' => $this->nome, 'password' => 'SenhaGestor1', 'role' => 'instructor',
        ]);

        $res->assertOk()
            ->assertJsonPath('user.id', $gestorId)
            ->assertJsonPath('user.role', 'instructor');
    }

    public function test_papel_errado_por_email_tambem_nao_entra(): void
    {
        $this->conta('student', 'SenhaAluno1');
        $email = DB::table('User')->where('name', $this->nome)->value('email');

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'SenhaAluno1', 'role' => 'admin'])
            ->assertStatus(401);
    }

    public function test_papel_invalido_e_recusado(): void
    {
        $this->postJson('/api/auth/login', ['name' => $this->nome, 'password' => 'x', 'role' => 'superuser'])
            ->assertStatus(400);
    }

    public function test_sem_papel_o_login_continua_como_antes(): void
    {
        // CPF e e-mail não mandam papel; o contrato antigo segue valendo.
        $this->conta('instructor', 'SenhaGestor1');
        $email = DB::table('User')->where('name', $this->nome)->value('email');

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'SenhaGestor1'])
            ->assertOk()
            ->assertJsonPath('user.role', 'instructor');
    }
}
