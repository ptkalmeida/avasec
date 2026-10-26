<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Services\SitePageContentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/aluno/duvidas` em Blade — as mesmas dúvidas do portal, e a saída para uma
 * pessoa quando elas não bastam.
 */
final class AjudaDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/duvidas')->assertRedirect('/entrar');
    }

    /** Uma fonte só: o que a coordenação edita no admin aparece aqui também. */
    public function test_mostra_as_perguntas_do_conteudo_editavel(): void
    {
        $aluno = $this->makeStudent('Aluno Ajuda Lista');
        $perguntas = app(SitePageContentService::class)->get('duvidas')['items'];
        $this->assertNotEmpty($perguntas);

        $resposta = $this->como($aluno['token'])->get('/aluno/duvidas');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        foreach ($perguntas as $faq) {
            $resposta->assertSee(e($faq['question']), false);
        }
    }

    /** As respostas escritas no código do React descreviam o que não existe. */
    public function test_nao_traz_as_respostas_que_descreviam_o_que_nao_existe(): void
    {
        $aluno = $this->makeStudent('Aluno Ajuda Falsas');

        $this->como($aluno['token'])->get('/aluno/duvidas')
            ->assertDontSee('Tolerância Acadêmica de 5 Dias', escape: false)
            ->assertDontSee('arquivada preventivamente', escape: false)
            ->assertDontSee('em até 24 horas', escape: false);
    }

    public function test_quem_nao_achou_a_resposta_chega_ao_suporte(): void
    {
        $aluno = $this->makeStudent('Aluno Ajuda Suporte');

        $this->como($aluno['token'])->get('/aluno/duvidas')
            ->assertSee('href="/aluno/suporte"', escape: false)
            ->assertDontSee('href="/aluno/mensagens"', escape: false);
    }

    public function test_sem_mensagens_diretas_a_tela_nao_oferece_o_suporte(): void
    {
        config(['features.mensagensDiretas' => false]);
        $aluno = $this->makeStudent('Aluno Ajuda Sem Canal');

        $this->como($aluno['token'])->get('/aluno/duvidas')
            ->assertOk()
            ->assertDontSee('Falar com o gestor do curso', escape: false);
    }
}
