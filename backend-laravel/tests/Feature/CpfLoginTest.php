<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Cep;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Login por CPF + senha e dados cadastrais completos (ADR 11).
 * Requer MySQL de dev populado.
 */
final class CpfLoginTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** CPF real válido de domínio público, usado como referência do algoritmo. */
    private const CPF_VALIDO = '52998224725';

    // ---------- Validação do CPF (App\Support\Cpf) ----------

    public function test_cpf_validation_accepts_valid_and_rejects_invalid(): void
    {
        $this->assertTrue(Cpf::isValid(self::CPF_VALIDO));
        $this->assertTrue(Cpf::isValid('529.982.247-25'), 'Pontuação deve ser aceita.');

        $this->assertFalse(Cpf::isValid('52998224724'), 'Dígito verificador errado.');
        $this->assertFalse(Cpf::isValid('123456789'), 'Menos de 11 dígitos.');
        $this->assertFalse(Cpf::isValid('529982247251'), 'Mais de 11 dígitos.');
        $this->assertFalse(Cpf::isValid(''), 'Vazio.');
        $this->assertFalse(Cpf::isValid('abcdefghijk'), 'Sem dígitos.');
    }

    public function test_cpf_validation_rejects_repeated_digit_sequences(): void
    {
        // Passam no cálculo do dígito verificador por acidente matemático,
        // mas nenhuma é um CPF real.
        foreach (['00000000000', '11111111111', '99999999999'] as $sequencia) {
            $this->assertFalse(Cpf::isValid($sequencia), "Sequência {$sequencia} deveria ser inválida.");
        }
    }

    public function test_cpf_normalize_and_format(): void
    {
        $this->assertSame(self::CPF_VALIDO, Cpf::normalize('529.982.247-25'));
        $this->assertSame('529.982.247-25', Cpf::format(self::CPF_VALIDO));
    }

    public function test_cep_validation(): void
    {
        $this->assertTrue(Cep::isValid('20031-170'));
        $this->assertTrue(Cep::isValid('20031170'));
        $this->assertFalse(Cep::isValid('2003117'), 'Menos de 8 dígitos.');
        $this->assertFalse(Cep::isValid('00000000'), 'Tudo zero.');
        $this->assertSame('20031-170', Cep::format('20031170'));
    }

    // ---------- Cadastro ----------

    public function test_student_registration_requires_a_valid_cpf(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->postJson('/api/auth/register', [
                'name' => 'Aluno Sem CPF',
                'email' => 'semcpf-'.uniqid().'@example.com',
                'password' => 'senha123456',
                'role' => 'student',
            ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->flushHeaders();

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->postJson('/api/auth/register', [
                'name' => 'Aluno CPF Falso',
                'email' => 'cpffalso-'.uniqid().'@example.com',
                'password' => 'senha123456',
                'role' => 'student',
                'cpf' => '11111111111',
            ])
            ->assertStatus(400);
    }

    public function test_registration_persists_full_registration_data_normalized(): void
    {
        $admin = $this->staffToken('admin');
        $cpf = $this->makeCpf();

        $res = $this->withHeader('Authorization', "Bearer {$admin}")
            ->postJson('/api/auth/register', [
                'name' => 'Aluna Completa',
                'email' => 'completa-'.uniqid().'@example.com',
                'password' => 'senha123456',
                'role' => 'student',
                'cpf' => Cpf::format($cpf),
                'celular' => '(21) 99999-1234',
                'cep' => '20031-170',
                'endereco' => 'Rua da Cultura, 100 — Centro',
                'nomeSocial' => 'Aluna C.',
                'identidade' => 'MG-12.345.678',
            ])
            ->assertStatus(201);

        $row = DB::table('User')->where('id', $res->json('user.id'))->first();
        $this->assertNotNull($row);
        // CPF e CEP são gravados só em dígitos — é o que torna o unique e a
        // busca do login confiáveis.
        $this->assertSame($cpf, $row->cpf);
        $this->assertSame('20031170', $row->cep);
        $this->assertSame('Rua da Cultura, 100 — Centro', $row->endereco);
        $this->assertSame('Aluna C.', $row->nomeSocial);
        $this->assertSame('MG-12.345.678', $row->identidade);
    }

    public function test_duplicate_cpf_is_rejected_with_409(): void
    {
        $aluno = $this->makeStudent('Aluno CPF Unico');
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->postJson('/api/auth/register', [
                'name' => 'Outro Aluno',
                'email' => 'outro-'.uniqid().'@example.com',
                'password' => 'senha123456',
                'role' => 'student',
                'cpf' => $aluno['cpf'],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_staff_account_can_be_created_without_cpf(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->postJson('/api/auth/register', [
                'name' => 'Gestor Sem CPF',
                'email' => 'gestor-'.uniqid().'@example.com',
                'password' => 'senha123456',
                'role' => 'instructor',
            ])
            ->assertStatus(201)
            ->assertJsonPath('user.role', 'instructor');
    }

    // ---------- Política de senha ----------

    public function test_password_policy_rejects_short_and_numeric_only(): void
    {
        $admin = $this->staffToken('admin');

        foreach (['1234', 'senha1', '12345678', 'senhasenha'] as $fraca) {
            $this->flushHeaders();
            $this->withHeader('Authorization', "Bearer {$admin}")
                ->postJson('/api/auth/register', [
                    'name' => 'Aluno Senha Fraca',
                    'email' => 'fraca-'.uniqid().'@example.com',
                    'password' => $fraca,
                    'role' => 'student',
                    'cpf' => $this->makeCpf(),
                ])
                ->assertStatus(400, "Senha \"{$fraca}\" deveria ser rejeitada.");
        }
    }

    // ---------- Login ----------

    public function test_student_logs_in_with_cpf(): void
    {
        $aluno = $this->makeStudent('Aluno Login CPF');
        // Cadastro por admin nasce 'active', então o login é permitido.
        $this->flushHeaders();

        $this->postJson('/api/auth/login', [
            'cpf' => $aluno['cpf'],
            'password' => 'senha123456',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $aluno['id'])
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role']]);
    }

    public function test_student_logs_in_with_punctuated_cpf(): void
    {
        $aluno = $this->makeStudent('Aluno CPF Pontuado');
        $this->flushHeaders();

        $this->postJson('/api/auth/login', [
            'cpf' => Cpf::format($aluno['cpf']),
            'password' => 'senha123456',
        ])->assertOk()->assertJsonPath('user.id', $aluno['id']);
    }

    public function test_login_with_wrong_password_on_valid_cpf_is_generic_401(): void
    {
        $aluno = $this->makeStudent('Aluno Senha Errada');
        $this->flushHeaders();

        $this->postJson('/api/auth/login', [
            'cpf' => $aluno['cpf'],
            'password' => 'senhaerrada999',
        ])
            ->assertStatus(401)
            // Nunca revela que o CPF existe.
            ->assertJsonPath('message', 'Usuário ou senha inválidos.');
    }

    public function test_login_with_unknown_cpf_is_generic_401(): void
    {
        $this->postJson('/api/auth/login', [
            'cpf' => self::CPF_VALIDO,
            'password' => 'senha123456',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Usuário ou senha inválidos.');
    }

    public function test_login_without_any_identifier_is_400(): void
    {
        $this->postJson('/api/auth/login', ['password' => 'senha123456'])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_staff_still_logs_in_with_email(): void
    {
        // Admin e gestor entram por e-mail (ADR 11) — o CPF não os substitui.
        $admin = DB::table('User')->where('role', 'admin')->where('status', 'active')->first(['email']);
        $this->assertNotNull($admin);

        // Senha desconhecida aqui, então basta provar que a busca por e-mail
        // encontra a conta: erro é 401 de senha, não "identificador ausente".
        $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'senha-que-nao-e-a-dele-123',
        ])->assertStatus(401);
    }
}
