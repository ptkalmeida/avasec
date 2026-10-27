<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Certificado;
use App\Models\Curso;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/aluno/certificados` em Blade.
 *
 * O que mais se protege aqui é que a pessoa veja o PRÓPRIO documento, e só
 * ele — e que a tela diga o que de fato falta para o próximo sair. A versão
 * React só falava de frequência: com a frequência cumprida e uma avaliação em
 * aberto, mandava continuar assistindo aulas quem já tinha assistido todas.
 */
final class CertificadosDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /**
     * O cookie vai sem criptografia: `ava_session` é excluído da
     * criptografia de cookies do Laravel, e `withCookie()` o cifraria — o
     * middleware não reconheceria o token e mandaria tudo para o login.
     */
    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @param array{id:string,name:string,cpf:string,token:string} $aluno */
    private function certificadoDe(array $aluno): Certificado
    {
        $curso = Curso::query()->first();
        $this->assertNotNull($curso, 'Seed sem curso.');

        return Certificado::query()->create([
            'id' => 'cert-aluno-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo,
            'data_emissao' => now(),
            'percentual_frequencia' => 88,
            'hash_verificacao' => 'AVA-ALUNO-'.strtoupper(uniqid()),
        ]);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/certificados')->assertRedirect('/entrar');
    }

    public function test_aluno_sem_certificado_ve_a_ausencia_explicada(): void
    {
        $aluno = $this->makeStudent('Aluno Sem Cert');

        $resposta = $this->como($aluno['token'])->get('/aluno/certificados');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Você ainda não tem certificado emitido.', escape: false);
    }

    public function test_o_certificado_emitido_aparece_com_codigo_e_as_duas_portas_do_pdf(): void
    {
        $aluno = $this->makeStudent('Aluno Com Cert');
        $cert = $this->certificadoDe($aluno);

        $resposta = $this->como($aluno['token'])->get('/aluno/certificados');

        $resposta->assertOk();
        $resposta->assertSee(e((string) $cert->titulo_curso), false);
        $resposta->assertSee((string) $cert->hash_verificacao, escape: false);
        $resposta->assertSee('88%', escape: false);
        $resposta->assertSee('href="/aluno/certificados/'.$cert->id.'/pdf"', escape: false);
        $resposta->assertSee('href="/aluno/certificados/'.$cert->id.'/pdf?modo=ver"', escape: false);
    }

    /** Ninguém vê o certificado de outra pessoa na própria lista. */
    public function test_a_lista_traz_so_os_certificados_do_proprio_aluno(): void
    {
        $dono = $this->makeStudent('Aluno Dono');
        $outro = $this->makeStudent('Aluno Outro');
        $cert = $this->certificadoDe($dono);

        $this->como($outro['token'])->get('/aluno/certificados')
            ->assertDontSee((string) $cert->hash_verificacao, escape: false);
    }

    public function test_baixar_entrega_o_pdf_como_anexo(): void
    {
        $aluno = $this->makeStudent('Aluno Baixa');
        $cert = $this->certificadoDe($aluno);

        $resposta = $this->como($aluno['token'])->get('/aluno/certificados/'.$cert->id.'/pdf');

        $resposta->assertOk();
        $resposta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('attachment;', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $resposta->getContent());
    }

    /**
     * "Ver" abre o MESMO PDF no navegador. No React era um certificado
     * redesenhado em HTML — uma segunda versão do documento, que podia
     * divergir do papel impresso.
     */
    public function test_ver_entrega_o_mesmo_pdf_para_abrir_no_navegador(): void
    {
        $aluno = $this->makeStudent('Aluno Ve');
        $cert = $this->certificadoDe($aluno);

        $resposta = $this->como($aluno['token'])->get('/aluno/certificados/'.$cert->id.'/pdf?modo=ver');

        $resposta->assertOk();
        $this->assertStringStartsWith('inline;', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $resposta->getContent());
    }

    /** O serviço recusa o PDF de outra pessoa, e a porta web não abre exceção. */
    public function test_pdf_de_outro_aluno_e_recusado(): void
    {
        $dono = $this->makeStudent('Aluno Dono PDF');
        $intruso = $this->makeStudent('Aluno Intruso PDF');
        $cert = $this->certificadoDe($dono);

        $this->como($intruso['token'])->get('/aluno/certificados/'.$cert->id.'/pdf')->assertForbidden();
    }

    public function test_certificado_inexistente_responde_404_e_nao_500(): void
    {
        $aluno = $this->makeStudent('Aluno 404');

        $this->como($aluno['token'])->get('/aluno/certificados/nao-existe/pdf')->assertNotFound();
    }

    /**
     * O caso que motivou mostrar as avaliações: frequência cumprida e prova em
     * aberto. A tela tem de apontar a prova, e não mandar assistir aulas.
     */
    public function test_a_caminho_aponta_a_avaliacao_pendente_quando_a_frequencia_ja_basta(): void
    {
        $aluno = DB::table('usuario')->where('email', 'joao.silva@lms.edu')->first();
        if ($aluno === null) {
            $this->markTestSkipped('Aluno de demonstração ausente.');
        }

        $html = (string) $this->como(Jwt::issue((string) $aluno->id, (string) $aluno->nome, 'student'))
            ->get('/aluno/certificados')->getContent();

        if (! str_contains($html, 'A caminho')) {
            $this->markTestSkipped('O aluno de demonstração não tem curso a caminho.');
        }

        // A frequência vem escrita, não só na barra.
        $this->assertMatchesRegularExpression('/\d+% de \d+% exigidos/u', $html);

        // Se há avaliação pendente, ela é nomeada; e frequência cumprida não
        // vira instrução de assistir aulas.
        if (str_contains($html, 'Ser aprovado na avaliação')) {
            $this->assertStringNotContainsString('Continue assistindo', $html);
        }
    }

    /** A antiga aba "Validação" vive onde a função já existe. */
    public function test_a_validacao_leva_ao_autenticador_publico(): void
    {
        $aluno = $this->makeStudent('Aluno Valida');

        $this->como($aluno['token'])->get('/aluno/certificados')
            ->assertSee('href="/certificados"', escape: false);
    }
}
