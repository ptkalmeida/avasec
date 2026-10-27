<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\ConteudoPaginaSite;
use App\Models\Usuario;
use App\Services\SitePageContentService;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Páginas do Site" do painel administrativo (fatia 6b do M5). O que se grava
 * aqui aparece para qualquer visitante, então pesa: só o admin escreve, tudo é
 * texto (nunca vira HTML), e adicionar/remover/mover item não grava nada até
 * "Salvar e publicar".
 */
final class PaginasDoSiteAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function gravado(string $pagina): ?ConteudoPaginaSite
    {
        return ConteudoPaginaSite::query()->find($pagina);
    }

    public function test_so_o_admin_chega_e_escreve(): void
    {
        $aluno = $this->makeStudent('Aluno Paginas Acesso');
        $gestor = Usuario::query()->where('papel', 'instructor')->firstOrFail();
        $tokenDoGestor = Jwt::issue($gestor->id, $gestor->nome, 'instructor');

        // O visitante vem primeiro: o cookie de `como()` persiste no cliente de teste.
        $this->get('/admin/paginas-do-site')->assertRedirect('/entrar');
        $this->post('/admin/paginas-do-site/o-ava', ['topo' => ['titulo' => 'Invasão']])->assertRedirect('/entrar');
        $this->como($aluno['token'])->get('/admin/paginas-do-site')->assertRedirect('/aluno');
        $this->como($tokenDoGestor)->get('/admin/paginas-do-site')->assertRedirect('/inst');
        $this->como($tokenDoGestor)->post('/admin/paginas-do-site/o-ava', ['topo' => ['titulo' => 'Invasão']])->assertRedirect('/inst');

        $this->assertNotSame('Invasão', $this->gravado('o-ava')?->conteudo['titulo'] ?? null);
    }

    public function test_a_flag_desligada_responde_404(): void
    {
        config(['features.gestaoConteudoSite' => false]);

        $this->como($this->admin())->get('/admin/paginas-do-site')->assertNotFound();
        $this->como($this->admin())->post('/admin/paginas-do-site/o-ava', [])->assertNotFound();
    }

    public function test_mostra_as_sete_paginas_e_os_campos_do_schema(): void
    {
        $tela = $this->como($this->admin())->get('/admin/paginas-do-site');

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        foreach (['O que é o AVA', 'Certificados e Emissão', 'O Projeto Pedagógico', 'Notícias & Novidades', 'Dúvidas Frequentes', 'Calendário de Aulas ao Vivo', 'Orientações Gerais'] as $nome) {
            $tela->assertSee(str_replace('&', '&amp;', $nome), false);
        }
        $tela->assertSee('Título da página');
        $tela->assertSee('name="topo[titulo]"', false);
        $tela->assertSee('Salvar e publicar');
        $tela->assertSee('Ver esta página no site');
        $tela->assertSee('href="/o-ava"', false);
    }

    public function test_cada_pagina_mostra_os_campos_do_item_dela(): void
    {
        $token = $this->admin();

        $this->como($token)->get('/admin/paginas-do-site?pagina=duvidas')
            ->assertSee('Pergunta')->assertSee('Resposta');
        $this->como($token)->get('/admin/paginas-do-site?pagina=noticias')
            ->assertSee('Título da notícia')->assertSee('Etiqueta')->assertSee('Data exibida');
        $this->como($token)->get('/admin/paginas-do-site?pagina=calendario')
            ->assertSee('Quem ministra')->assertSee('Horário');
    }

    public function test_pagina_inventada_volta_para_a_primeira(): void
    {
        $this->como($this->admin())->get('/admin/paginas-do-site?pagina=inventada')->assertRedirect('/admin/paginas-do-site');
        $this->como($this->admin())->post('/admin/paginas-do-site/inventada', [])->assertNotFound();
    }

    public function test_salvar_publica_normaliza_e_descarta_item_vazio(): void
    {
        $this->como($this->admin())->post('/admin/paginas-do-site/duvidas', [
            'acao' => 'salvar',
            'topo' => ['eyebrow' => '  Apoio  ', 'titulo' => 'Título novo', 'descricao' => 'Abertura nova'],
            'itens' => [
                ['id' => 'q-1', 'question' => 'Primeira pergunta?', 'answer' => 'Primeira resposta.'],
                ['id' => 'q-2', 'question' => '', 'answer' => ''],
                ['id' => 'q-3', 'question' => 'Terceira?', 'answer' => 'Terceira.'],
            ],
        ])->assertRedirect('/admin/paginas-do-site?pagina=duvidas')->assertSessionHas('avisoDoSite');

        $conteudo = $this->gravado('duvidas')?->conteudo;
        $this->assertIsArray($conteudo);
        $this->assertSame('Apoio', $conteudo['eyebrow']);
        $this->assertSame('Título novo', $conteudo['titulo']);
        $this->assertCount(2, $conteudo['items']);
        $this->assertSame('q-1', $conteudo['items'][0]['id']);
        $this->assertSame('Terceira?', $conteudo['items'][1]['question']);

        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Edição de Conteúdo do Site')->where('detalhes', 'like', '%duvidas%')->exists());

        // É o que o serviço público entrega ao visitante, sem login.
        $this->assertSame('Título novo', app(SitePageContentService::class)->get('duvidas')['titulo']);
    }

    /** Conteúdo de página pública NUNCA vira HTML: o texto chega como texto e sai escapado. */
    public function test_marcacao_digitada_fica_texto_e_sai_escapada(): void
    {
        $token = $this->admin();

        $this->como($token)->post('/admin/paginas-do-site/o-ava', [
            'acao' => 'salvar',
            'topo' => ['titulo' => '<script>alert(1)</script> Título'],
            'itens' => [['id' => 'a-1', 'titulo' => '<img src=x onerror=alert(1)>', 'descricao' => 'ok']],
        ]);

        $tela = $this->como($token)->get('/admin/paginas-do-site?pagina=o-ava');
        $tela->assertDontSee('<script>alert(1)</script>', false);
        $tela->assertDontSee('<img src=x', false);
        $tela->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Título', false);
    }

    public function test_adicionar_remover_e_mover_nao_gravam_nada(): void
    {
        $token = $this->admin();
        $antes = $this->gravado('duvidas')?->conteudo;
        $rascunho = [
            'topo' => ['titulo' => 'Rascunho'],
            'itens' => [
                ['id' => 'a', 'question' => 'Primeira', 'answer' => 'um'],
                ['id' => 'b', 'question' => 'Segunda', 'answer' => 'dois'],
            ],
        ];

        // Adicionar: uma linha em branco a mais, no rascunho.
        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'adicionar'])
            ->assertRedirect('/admin/paginas-do-site?pagina=duvidas');
        $tela = $this->como($token)->get('/admin/paginas-do-site?pagina=duvidas');
        $tela->assertSee('name="itens[2][question]"', false);
        $tela->assertSee('Rascunho');

        // Mover para baixo o primeiro: a Segunda passa a vir antes.
        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'descer:0']);
        $html = (string) $this->como($token)->get('/admin/paginas-do-site?pagina=duvidas')->getContent();
        $this->assertLessThan(strpos($html, 'Primeira'), strpos($html, 'Segunda'));

        // Remover o primeiro do rascunho.
        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'remover:0']);
        $depois = $this->como($token)->get('/admin/paginas-do-site?pagina=duvidas');
        $depois->assertDontSee('Primeira');
        $depois->assertSee('Segunda');

        // Nada foi ao ar.
        $this->assertEquals($antes, $this->gravado('duvidas')?->conteudo);
    }

    public function test_o_limite_de_itens_da_pagina_e_respeitado(): void
    {
        $itens = [];
        for ($i = 0; $i < 9; $i++) {
            $itens[] = ['id' => 'x-'.$i, 'titulo' => 'Destaque '.$i, 'descricao' => 'd'];
        }

        // `o-ava` aceita no máximo 9.
        $this->como($this->admin())->post('/admin/paginas-do-site/o-ava', ['acao' => 'adicionar', 'topo' => ['titulo' => 'T'], 'itens' => $itens])
            ->assertSessionHasErrors('itens');
    }

    public function test_remover_ou_mover_indice_inexistente_nao_quebra(): void
    {
        $token = $this->admin();
        $rascunho = ['topo' => ['titulo' => 'T'], 'itens' => [['id' => 'a', 'question' => 'Só', 'answer' => 'uma']]];

        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'remover:99'])->assertRedirect();
        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'subir:0'])->assertRedirect();
        $this->como($token)->post('/admin/paginas-do-site/duvidas', $rascunho + ['acao' => 'descer:0'])->assertRedirect();
        $this->como($token)->get('/admin/paginas-do-site?pagina=duvidas')->assertOk()->assertSee('Só');
    }

    public function test_o_primeiro_botao_do_formulario_e_o_de_salvar(): void
    {
        $html = (string) $this->como($this->admin())->get('/admin/paginas-do-site')->getContent();

        $primeiro = strpos($html, 'name="acao"');
        $this->assertNotFalse($primeiro);
        $this->assertStringContainsString('value="salvar"', substr($html, $primeiro, 40));
    }

    public function test_campos_que_o_schema_nao_conhece_nao_sao_gravados(): void
    {
        $this->como($this->admin())->post('/admin/paginas-do-site/orientacoes', [
            'acao' => 'salvar',
            'topo' => ['titulo' => 'Orientações', 'campoInventado' => 'x'],
            'itens' => [['id' => 'o-1', 'titulo' => 'Diretriz', 'descricao' => 'd', 'campoInventado' => 'y']],
        ]);

        $conteudo = $this->gravado('orientacoes')?->conteudo;
        $this->assertIsArray($conteudo);
        $this->assertArrayNotHasKey('campoInventado', $conteudo);
        $this->assertArrayNotHasKey('campoInventado', $conteudo['items'][0]);
    }
}
