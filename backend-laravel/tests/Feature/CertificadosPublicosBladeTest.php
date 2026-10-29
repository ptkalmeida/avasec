<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Services\SitePageContentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\ComVerificacaoPublicaLigada;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/certificados` em Blade — critérios de emissão e autenticador público.
 *
 * Esta é a única tela do portal que uma pessoa de FORA da escola usa para
 * decidir alguma coisa: uma secretaria com o papel na mão conferindo se o
 * documento vale. Por isso o que mais se protege aqui não é o layout, e sim as
 * três respostas possíveis — válido, revogado e inexistente — chegarem
 * escritas, e não apenas coloridas.
 *
 * O caso do REVOGADO é o que justifica a consulta pública existir: sumir com o
 * documento responderia "não existe" sobre um papel que está impresso e pode
 * estar anexado a um processo (ADR 12).
 */
final class CertificadosPublicosBladeTest extends TestCase
{
    use ComVerificacaoPublicaLigada;
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        // O limitador é o mesmo da API (120/15min por IP) e dispararia ao longo
        // da suíte; o que ele protege está coberto no teste da rota de API.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function certificadoEmitido(string $nome): Certificado
    {
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $curso = Curso::query()->where('instrutor_id', $instrutor_id)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        $aluno = $this->makeStudent($nome);

        return Certificado::query()->create([
            'id' => 'cert-blade-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'usuario_id' => $aluno['id'],
            'curso_id' => $curso->id,
            'titulo_curso' => $curso->titulo,
            'data_emissao' => now(),
            'percentual_frequencia' => 92,
            'hash_verificacao' => 'AVA-BLADE-'.strtoupper(uniqid()),
        ]);
    }

    public function test_a_tela_responde_em_blade_e_traz_os_criterios_do_servico_de_conteudo(): void
    {
        $pagina = app(SitePageContentService::class)->get('certificados');

        $resposta = $this->get('/certificados');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee(e((string) $pagina['criteriaTitle']), false);
        $resposta->assertSee(e((string) $pagina['authenticatorTitle']), false);
        $resposta->assertSee(e((string) $pagina['noticeText']), false);

        foreach ($pagina['items'] as $criterio) {
            $resposta->assertSee(e((string) $criterio['titulo']), false);
        }
    }

    /** Sem consulta, a tela não afirma nada sobre documento nenhum. */
    public function test_sem_termo_nao_mostra_resultado(): void
    {
        $resposta = $this->get('/certificados');

        $resposta->assertDontSee('Certificado válido e homologado', escape: false);
        $resposta->assertDontSee('Código não encontrado', escape: false);
    }

    public function test_certificado_valido_sai_com_aluno_curso_e_registro(): void
    {
        $cert = $this->certificadoEmitido('Aluno Blade Valido');

        $resposta = $this->get('/certificados?verify='.$cert->hash_verificacao);

        $resposta->assertOk();
        $resposta->assertSee('Certificado válido e homologado', escape: false);
        $resposta->assertSee(e((string) $cert->nome_aluno), false);
        $resposta->assertSee(e((string) $cert->titulo_curso), false);
        $resposta->assertSee((string) $cert->hash_verificacao, escape: false);
        $resposta->assertSee('92%', escape: false);
    }

    /**
     * O documento revogado APARECE, dizendo que foi revogado, com o motivo
     * registrado. É a resposta de que quem confere precisa.
     */
    public function test_certificado_revogado_aparece_dizendo_que_nao_vale(): void
    {
        $cert = $this->certificadoEmitido('Aluno Blade Revogado');
        $adminId = DB::table('usuario')->where('papel', 'admin')->value('id');
        $cert->inativar((string) $adminId, 'Frequência lançada em duplicidade.');

        $resposta = $this->get('/certificados?verify='.$cert->hash_verificacao);

        $resposta->assertOk();
        $resposta->assertSee('Certificado revogado', escape: false);
        $resposta->assertSee('Frequência lançada em duplicidade.', escape: false);
        $resposta->assertDontSee('Certificado válido e homologado', escape: false);
        // Os dados do documento continuam visíveis: é com eles que se confere o
        // papel que está na mão.
        $resposta->assertSee(e((string) $cert->nome_aluno), false);
    }

    public function test_termo_sem_correspondencia_diz_que_nao_encontrou_e_repete_o_termo(): void
    {
        $resposta = $this->get('/certificados?verify=AVA-NAO-EXISTE-9999');

        $resposta->assertOk();
        $resposta->assertSee('Código não encontrado', escape: false);
        $resposta->assertSee('AVA-NAO-EXISTE-9999', escape: false);
    }

    /** O campo volta preenchido: quem errou um dígito corrige, não redigita. */
    public function test_o_termo_consultado_volta_no_campo(): void
    {
        $this->get('/certificados?verify=AVA-ALGUMA-COISA')
            ->assertSee('value="AVA-ALGUMA-COISA"', escape: false);
    }

    /**
     * O QR impresso no PDF aponta para `/?verify=<hash>`
     * (`CertificatePdfService`). Quando a raiz passou a ser Blade, o efeito do
     * React que levava esse parâmetro adiante deixou de existir, e o QR passou
     * a abrir a home sem responder nada. Este teste é o que impede isso de
     * acontecer de novo — há certificado impresso em circulação.
     */
    public function test_o_qr_impresso_continua_levando_a_consulta_ate_o_autenticador(): void
    {
        $cert = $this->certificadoEmitido('Aluno Blade QR');

        $this->get('/?verify='.$cert->hash_verificacao)
            ->assertRedirect('/certificados?verify='.urlencode((string) $cert->hash_verificacao));

        $this->followingRedirects()
            ->get('/?verify='.$cert->hash_verificacao)
            ->assertSee('Certificado válido e homologado', escape: false);
    }

    /** Raiz sem o parâmetro continua sendo a home, e não um desvio. */
    public function test_a_home_sem_verify_continua_sendo_a_home(): void
    {
        $this->get('/')->assertOk();
    }
}
