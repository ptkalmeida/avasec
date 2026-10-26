<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/aluno/perfil` em Blade — dados da conta, nome e senha.
 *
 * Duas coisas importam mais aqui: que mudar nome e senha exija a senha atual,
 * e que errar essa senha NÃO bloqueie a conta. O perfil do React confirmava
 * a senha fazendo login de novo, e login errado conta tentativa — com cinco,
 * a conta ficava bloqueada por quinze minutos.
 */
final class PerfilDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** A senha que o `SeedsIdentity` usa ao criar a conta. */
    private const SENHA = 'senha123456';

    protected function setUp(): void
    {
        parent::setUp();
        // O limitador `auth-password` é coberto nos testes da API; aqui ele
        // dispararia ao longo do arquivo.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/perfil')->assertRedirect('/entrar');
    }

    public function test_mostra_os_dados_reais_da_conta_com_o_cpf_mascarado(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Dados');
        $cpf = (string) User::query()->whereKey($aluno['id'])->value('cpf');

        $resposta = $this->como($aluno['token'])->get('/aluno/perfil');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee(e($aluno['name']), false);
        $resposta->assertSee(substr($cpf, 0, 3).'.***.***-'.substr($cpf, 9, 2), escape: false);
        // O CPF inteiro não sai na tela.
        $resposta->assertDontSee($cpf, escape: false);
    }

    /**
     * O que só existia no navegador não volta: o "e-mail" montado a partir do
     * nome, o selo Online/Offline e o histórico com assinaturas escritas no
     * código.
     */
    public function test_nao_traz_o_que_era_simulado_no_navegador(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Simulado');

        $resposta = $this->como($aluno['token'])->get('/aluno/perfil');

        $resposta->assertDontSee('@lms.edu', escape: false);
        $resposta->assertDontSee('Online', escape: false);
        $resposta->assertDontSee('Histórico Escolar Oficial', escape: false);
        $resposta->assertDontSee('Alessandro Pinto', escape: false);
    }

    public function test_nome_social_aparece_quando_existe(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Social');
        User::query()->whereKey($aluno['id'])->update(['nomeSocial' => 'Sam']);

        $this->como($aluno['token'])->get('/aluno/perfil')
            ->assertSee('Nome social', escape: false)
            ->assertSee('Sam', escape: false);
    }

    public function test_trocar_o_nome_com_a_senha_certa_grava_e_reflete_na_hora(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Renomeia');

        $this->como($aluno['token'])
            ->post('/aluno/perfil/nome', ['nome' => 'Nome Novo Do Aluno', 'senhaAtual' => self::SENHA])
            ->assertRedirect('/aluno/perfil');

        $this->assertSame('Nome Novo Do Aluno', User::query()->whereKey($aluno['id'])->value('name'));

        // O nome vem do banco a cada página, e não do token: o painel já
        // cumprimenta pelo nome novo com a MESMA sessão.
        $this->como($aluno['token'])->get('/aluno')->assertSee('Olá, Nome Novo Do Aluno', escape: false);
    }

    /** Certificado é documento: o nome nele é o da emissão. */
    public function test_trocar_o_nome_nao_reescreve_certificado_ja_emitido(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Cert');
        $curso = Course::query()->first();
        $this->assertNotNull($curso);
        $cert = Certificate::query()->create([
            'id' => 'cert-perfil-'.uniqid(),
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'courseTitle' => $curso->title,
            'issueDate' => now(),
            'attendancePercent' => 90,
            'verificationHash' => 'AVA-PERFIL-'.strtoupper(uniqid()),
        ]);

        $this->como($aluno['token'])
            ->post('/aluno/perfil/nome', ['nome' => 'Outro Nome Qualquer', 'senhaAtual' => self::SENHA]);

        $this->assertSame($aluno['name'], Certificate::query()->whereKey($cert->id)->value('studentName'));
    }

    public function test_trocar_o_nome_com_senha_errada_nao_grava(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Senha Errada');

        $this->como($aluno['token'])
            ->from('/aluno/perfil')
            ->post('/aluno/perfil/nome', ['nome' => 'Nome Que Nao Deve Entrar', 'senhaAtual' => 'errada000'])
            ->assertRedirect('/aluno/perfil')
            ->assertSessionHasErrorsIn('nome', 'senhaAtual');

        $this->assertSame($aluno['name'], User::query()->whereKey($aluno['id'])->value('name'));
    }

    /**
     * O defeito do React, provado ao contrário: errar a senha no perfil
     * repetidamente NÃO conta como tentativa de login e não bloqueia a conta.
     */
    public function test_errar_a_senha_no_perfil_nao_bloqueia_a_conta(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Bloqueio');

        for ($i = 0; $i < 6; $i++) {
            $this->como($aluno['token'])
                ->post('/aluno/perfil/nome', ['nome' => 'Tentativa '.$i, 'senhaAtual' => 'errada000']);
        }

        $conta = User::query()->whereKey($aluno['id'])->first(['failedLoginAttempts', 'lockedUntil']);
        $this->assertNotNull($conta);
        $this->assertSame(0, (int) $conta->failedLoginAttempts);
        $this->assertNull($conta->lockedUntil);
    }

    public function test_trocar_a_senha_com_a_senha_atual_certa_vale_no_proximo_login(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Nova Senha');

        $this->como($aluno['token'])
            ->post('/aluno/perfil/senha', [
                'senhaAtual' => self::SENHA,
                'novaSenha' => 'novasenha2026',
                'novaSenha_confirmation' => 'novasenha2026',
            ])
            ->assertRedirect('/aluno/perfil');

        $hash = (string) User::query()->whereKey($aluno['id'])->value('passwordHash');
        $this->assertTrue(password_verify('novasenha2026', $hash));
        $this->assertFalse(password_verify(self::SENHA, $hash));
    }

    public function test_senha_atual_errada_nao_troca_a_senha(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Senha Atual Errada');

        $this->como($aluno['token'])
            ->from('/aluno/perfil')
            ->post('/aluno/perfil/senha', [
                'senhaAtual' => 'naoeessa1',
                'novaSenha' => 'novasenha2026',
                'novaSenha_confirmation' => 'novasenha2026',
            ])
            ->assertSessionHasErrorsIn('senha', 'senhaAtual');

        $hash = (string) User::query()->whereKey($aluno['id'])->value('passwordHash');
        $this->assertTrue(password_verify(self::SENHA, $hash));
    }

    /**
     * A regra é a do servidor: 8 caracteres, uma letra e um número. O React
     * dizia "mínimo 6" e o servidor recusava com 7.
     */
    public function test_nova_senha_fora_da_regra_e_recusada_e_a_regra_e_dita_antes(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Regra');

        $this->como($aluno['token'])->get('/aluno/perfil')
            ->assertSee('Pelo menos 8 caracteres, com ao menos uma letra e um número.', escape: false);

        foreach (['curta1', 'semnumeroaqui', '12345678'] as $ruim) {
            $this->como($aluno['token'])
                ->from('/aluno/perfil')
                ->post('/aluno/perfil/senha', [
                    'senhaAtual' => self::SENHA,
                    'novaSenha' => $ruim,
                    'novaSenha_confirmation' => $ruim,
                ])
                ->assertSessionHasErrorsIn('senha', 'novaSenha');
        }
    }

    public function test_confirmacao_diferente_e_recusada(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Confirma');

        $this->como($aluno['token'])
            ->from('/aluno/perfil')
            ->post('/aluno/perfil/senha', [
                'senhaAtual' => self::SENHA,
                'novaSenha' => 'novasenha2026',
                'novaSenha_confirmation' => 'outracoisa2026',
            ])
            ->assertSessionHasErrorsIn('senha', 'novaSenha');
    }

    /** As duas alterações ficam na trilha de auditoria, como na API. */
    public function test_as_alteracoes_ficam_auditadas(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Auditoria');
        $antes = (int) DB::table('SecurityLog')->count();

        $this->como($aluno['token'])
            ->post('/aluno/perfil/nome', ['nome' => 'Nome Auditado', 'senhaAtual' => self::SENHA]);

        $this->assertGreaterThan($antes, (int) DB::table('SecurityLog')->count());
        $this->assertTrue(
            DB::table('SecurityLog')->where('action', 'Alteração de Nome')->exists(),
            'A troca de nome não deixou registro na auditoria.',
        );
    }

    /**
     * O submenu "Cursos concluídos" não depende de a página lembrar de passar
     * a lista: o perfil não a passa, e o menu a calcula. Antes, a omissão
     * fazia o menu dizer "Nenhum curso concluído ainda" para quem concluiu.
     */
    public function test_o_menu_mostra_os_cursos_concluidos_mesmo_sem_a_pagina_passar_a_lista(): void
    {
        $aluno = $this->makeStudent('Aluno Perfil Menu');
        $curso = Course::query()->where('status', 1)->first();
        $this->assertNotNull($curso);

        DB::table('StudentEnrollment')->insert([
            'id' => (string) Str::uuid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'enrolledCourseId' => null,
            'completedCourseIds' => json_encode([$curso->id]),
            'canMultiEnroll' => false,
            'extraCourseIds' => json_encode([]),
        ]);

        $resposta = $this->como($aluno['token'])->get('/aluno/perfil');

        $resposta->assertSee(e((string) $curso->title), false);
        $resposta->assertDontSee('Nenhum curso concluído ainda', escape: false);
    }
}
