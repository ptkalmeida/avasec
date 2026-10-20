<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\LibraryItem;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/aluno/biblioteca` em Blade.
 *
 * O que importa: o aluno vê só o que está no ar para ele, e item sem endereço
 * de verdade não oferece um "Baixar" que não baixa nada.
 */
final class BibliotecaDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @param array<string, mixed> $campos */
    private function item(string $titulo, array $campos = []): LibraryItem
    {
        $item = LibraryItem::query()->create(array_merge([
            'id' => 'lib-teste-'.uniqid(),
            'title' => $titulo,
            'type' => 'pdf',
            'category' => 'Teste',
            'description' => 'Descrição de '.$titulo,
            'url' => '/uploads/material-'.uniqid().'.pdf',
        ], $campos));

        return $item;
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/biblioteca')->assertRedirect('/entrar');
    }

    public function test_e_servida_pelo_servidor_e_lista_o_material_publicado(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Lista');
        $item = $this->item('Apostila Publicada de Teste');

        $resposta = $this->como($aluno['token'])->get('/aluno/biblioteca');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Apostila Publicada de Teste', escape: false);
        $resposta->assertSee('href="'.$item->url.'"', escape: false);
        $resposta->assertSee('Baixar arquivo', escape: false);
    }

    /** O React mostrava tudo: a API da biblioteca não aplica a escada. */
    public function test_item_restrito_ou_em_rascunho_nao_aparece_para_o_aluno(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Escada');
        $restrito = $this->item('Material Restrito de Teste');
        $rascunho = $this->item('Material Rascunho de Teste');
        LibraryItem::query()->whereKey($restrito->id)->update(['status' => Visibilidade::RESTRITO]);
        LibraryItem::query()->whereKey($rascunho->id)->update(['status' => Visibilidade::RASCUNHO]);

        $this->como($aluno['token'])->get('/aluno/biblioteca')
            ->assertDontSee('Material Restrito de Teste', escape: false)
            ->assertDontSee('Material Rascunho de Teste', escape: false);
    }

    public function test_item_inativado_nao_aparece(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Inativo');
        $item = $this->item('Material Inativado de Teste');
        $item->inativar($aluno['id'], 'teste');

        $this->como($aluno['token'])->get('/aluno/biblioteca')
            ->assertDontSee('Material Inativado de Teste', escape: false);
    }

    /** Os seis itens de demonstração têm `#`: não viram link morto. */
    public function test_item_sem_endereco_de_verdade_diz_que_ainda_nao_esta_disponivel(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Sem Url');
        $this->item('Material Sem Endereco Teste', ['url' => '#']);

        $resposta = $this->como($aluno['token'])->get('/aluno/biblioteca');

        $resposta->assertSee('Material Sem Endereco Teste', escape: false);
        $resposta->assertSee('Arquivo ainda não disponível', escape: false);
        $resposta->assertDontSee('href="#"', escape: false);
    }

    public function test_endereco_perigoso_nunca_vira_link(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Js');
        $this->item('Material Perigoso Teste', ['url' => 'javascript:alert(1)']);

        $this->como($aluno['token'])->get('/aluno/biblioteca')
            ->assertDontSee('javascript:alert', escape: false);
    }

    public function test_com_materiais_desligado_a_tela_e_o_item_do_menu_somem(): void
    {
        config(['features.materiaisComplementares' => false]);
        $aluno = $this->makeStudent('Aluno Biblioteca Flag');

        $this->como($aluno['token'])->get('/aluno/biblioteca')->assertNotFound();
        $this->como($aluno['token'])->get('/aluno/perfil')
            ->assertDontSee('href="/aluno/biblioteca"', escape: false);
    }

    /**
     * A barra das telas estreitas é uma segunda lista do mesmo menu, e ficou
     * apontando o suporte para `/aluno/mensagens` depois de o menu largo ter
     * sido corrigido. Nenhum link da área do aluno pode levar à tela fechada.
     */
    public function test_nenhum_menu_leva_a_tela_de_mensagens_fechada(): void
    {
        $aluno = $this->makeStudent('Aluno Biblioteca Menus');

        $resposta = $this->como($aluno['token'])->get('/aluno/biblioteca');

        $resposta->assertDontSee('href="/aluno/mensagens"', escape: false);
        $resposta->assertSee('href="/aluno/suporte"', escape: false);
    }
}
