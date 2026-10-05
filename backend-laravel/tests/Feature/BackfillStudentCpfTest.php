<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Contas de aluno criadas antes da ADR 11 ficaram com `cpf = NULL` — e como a
 * aba "Aluno" da tela de acesso pede CPF e nada mais, elas não tinham nenhum
 * caminho de login. O comando regulariza essas contas em ambiente de teste.
 */
final class BackfillStudentCpfTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** Cria um aluno e zera o CPF, reproduzindo a conta legada. */
    private function alunoSemCpf(): User
    {
        $aluno = $this->makeStudent('Aluno Legado');
        DB::table('User')->where('id', $aluno['id'])->update(['cpf' => null]);

        return User::query()->findOrFail($aluno['id']);
    }

    public function test_atribui_cpf_valido_e_permite_login_da_conta_legada(): void
    {
        $user = $this->alunoSemCpf();
        $this->assertNull($user->cpf);

        $this->artisan('avasec:backfill-student-cpf')->assertSuccessful();

        $cpf = User::query()->findOrFail($user->id)->cpf;
        $this->assertIsString($cpf);
        // O objeto do comando: um CPF que a tela de login aceita de verdade.
        $this->assertTrue(Cpf::isValid($cpf), "CPF gerado inválido: {$cpf}");
        $this->assertSame(Cpf::normalize($cpf), $cpf, 'CPF precisa ser gravado normalizado.');

        $this->postJson('/api/auth/login', ['cpf' => $cpf, 'password' => 'senha123456'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        $user = $this->alunoSemCpf();

        $this->artisan('avasec:backfill-student-cpf', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull(User::query()->findOrFail($user->id)->cpf);
    }

    public function test_rodar_duas_vezes_nao_troca_o_cpf_de_ninguem(): void
    {
        $user = $this->alunoSemCpf();

        $this->artisan('avasec:backfill-student-cpf')->assertSuccessful();
        $primeiro = User::query()->findOrFail($user->id)->cpf;

        $this->artisan('avasec:backfill-student-cpf')->assertSuccessful();

        // Idempotente: quem já tem CPF não é tocado — o CPF é a credencial de
        // login, trocá-lo a cada execução trancaria o aluno fora.
        $this->assertSame($primeiro, User::query()->findOrFail($user->id)->cpf);
    }

    public function test_nao_toca_em_aluno_que_ja_tem_cpf(): void
    {
        $aluno = $this->makeStudent('Aluno Com Cpf');

        $this->artisan('avasec:backfill-student-cpf')->assertSuccessful();

        $this->assertSame($aluno['cpf'], User::query()->findOrFail($aluno['id'])->cpf);
    }
}
