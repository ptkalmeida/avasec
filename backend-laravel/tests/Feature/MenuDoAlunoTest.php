<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Services\MenuDoAluno;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * O menu da área do aluno (ADR 026).
 *
 * O que este arquivo prende são as decisões de 27/10/2026, que mudaram o desenho de 19/10: o menu
 * lista TODOS os cursos em andamento, "Continuar estudando" leva à primeira aula pendente,
 * "Avaliações" é item do menu (e saiu da página do curso), os rótulos mudaram, e "Ajuda" e
 * "Suporte pedagógico" são um item só, com duas abas. E o que o redesenho aprovado continua
 * exigindo: "Cursos concluídos" é submenu, a lista de aulas aparece uma vez, e o percentual nunca
 * vira texto.
 *
 * Os cursos são criados aqui, dentro da transação: o resultado não depende de quais cursos o banco
 * de desenvolvimento tem ativos naquele dia.
 */
final class MenuDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private int $sequencia = 0;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @param list<string> $aulas títulos das aulas, na ordem da grade */
    private function curso(string $titulo, array $aulas = []): Curso
    {
        $id = 'course-'.(9_100_000_000_000 + ++$this->sequencia);

        $curso = new Curso;
        $curso->forceFill([
            'id' => $id, 'titulo' => $titulo, 'slug' => 'menu-'.$id, 'categoria' => 'Teste',
            'descricao' => 'Curso de teste do menu.', 'nome_instrutor' => 'Instrutor', 'miniatura' => '',
            'status' => 1, 'vigencia_ate' => '2099-12-31', 'vencimento_contrato_texto' => '2099-12-31',
        ])->save();

        foreach ($aulas as $i => $tituloDaAula) {
            Aula::query()->create([
                'id' => $id.'-a'.($i + 1), 'curso_id' => $id, 'titulo' => $tituloDaAula,
                'conteudo' => 'x', 'duracao' => '10 min', 'ordem' => $i + 1,
            ]);
        }

        return $curso;
    }

    /**
     * Um aluno com `$principal` como matrícula e, se houver, os `$extras` como matrícula concorrente.
     *
     * @param  list<string>  $extras
     * @return array{id: string, nome: string, cpf: string, token: string}
     */
    private function aluno(?Curso $principal, array $extras = [], array $concluidos = []): array
    {
        $aluno = $this->makeStudent('Aluno Menu');

        if ($principal !== null) {
            $this->matricular($aluno, $principal->id);
            Matricula::query()->where('usuario_id', $aluno['id'])->update([
                'cursos_extras_ids' => $extras,
                'cursos_concluidos_ids' => $concluidos,
            ]);
        }

        return $aluno;
    }

    private function concluir(array $aluno, Curso $curso, int ...$posicoes): void
    {
        ProgressoAluno::query()->create([
            'id' => (string) Str::uuid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $curso->id,
            'aulas_concluidas' => array_map(static fn (int $n): string => $curso->id.'-a'.$n, $posicoes),
            'sessoes_ao_vivo_assistidas' => [],
        ]);
    }

    /** O HTML do menu largo, sem o do menu das telas estreitas nem o conteúdo da página. */
    private function menuLargo(string $html): string
    {
        $this->assertSame(1, preg_match('#<nav aria-label="Navegação da área do aluno" .*?</nav>#s', $html, $m), 'O menu largo não foi desenhado.');

        return $m[0];
    }

    private function menuEstreito(string $html): string
    {
        $this->assertSame(1, preg_match('#<nav aria-label="Navegação da área do aluno \(telas estreitas\)".*?</nav>#s', $html, $m), 'O menu estreito não foi desenhado.');

        return $m[0];
    }

    // ---------- os cursos no menu ----------

    public function test_o_menu_lista_todos_os_cursos_em_andamento_com_a_contagem_de_aulas(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1', 'Aula 2', 'Aula 3']);
        $b = $this->curso('Curso Menu Beta', ['Aula 1', 'Aula 2']);
        $aluno = $this->aluno($a, [$b->id]);
        $this->concluir($aluno, $a, 1);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringContainsString('Meus cursos', $menu);
        $this->assertStringContainsString('Curso Menu Alfa', $menu);
        $this->assertStringContainsString('Curso Menu Beta', $menu);
        // A contagem que a skill manda exibir — e só ela: o percentual nunca vira texto.
        $this->assertStringContainsString('1 de 3 aulas concluídas', $menu);
        $this->assertStringContainsString('0 de 2 aulas concluídas', $menu);
        $this->assertDoesNotMatchRegularExpression('/>\s*\d{1,3}%\s*</u', $menu);
    }

    public function test_com_um_curso_so_nao_ha_grupo_meus_cursos_nem_submenu_de_concluidos_vazio(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1', 'Aula 2']);
        $aluno = $this->aluno($a);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringNotContainsString('Meus cursos', $menu);
        $this->assertStringNotContainsString('Cursos concluídos', $menu);
        $this->assertSame(1, substr_count($menu, 'href="/aluno/curso/menu-'.$a->id.'"'));
        $this->assertStringContainsString('0 de 2 aulas concluídas', $menu);
    }

    public function test_dentro_da_aula_alvo_o_continuar_estudando_nao_repete_a_pagina_atual(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1', 'Aula 2']);
        $aluno = $this->aluno($a);

        $naAula = (string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id.'/aula/'.$a->id.'-a1')->assertOk()->getContent();

        $this->assertStringNotContainsString('Continuar estudando', $this->menuLargo($naAula));
        $this->assertStringNotContainsString('Continuar estudando', $this->menuEstreito($naAula));
    }

    public function test_o_curso_em_tela_fica_marcado_e_os_outros_nao(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $b = $this->curso('Curso Menu Beta', ['Aula 1']);
        $aluno = $this->aluno($a, [$b->id]);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$b->id)->assertOk()->getContent());

        $this->assertMatchesRegularExpression('#<a href="/aluno/curso/menu-'.$b->id.'"\s+aria-current="page"#', $menu);
        $this->assertDoesNotMatchRegularExpression('#<a href="/aluno/curso/menu-'.$a->id.'"\s+aria-current#', $menu);
    }

    public function test_curso_inativado_nao_aparece_no_menu(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $b = $this->curso('Curso Menu Retirado', ['Aula 1']);
        $aluno = $this->aluno($a, [$b->id]);
        $b->inativar(null, 'teste do menu');

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringContainsString('Curso Menu Alfa', $menu);
        $this->assertStringNotContainsString('Curso Menu Retirado', $menu);
    }

    public function test_sem_curso_em_andamento_o_menu_diz_isso_e_nao_oferece_continuar(): void
    {
        $aluno = $this->aluno(null);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringContainsString('Você ainda não tem curso em andamento.', $menu);
        $this->assertStringNotContainsString('Continuar estudando', $menu);
    }

    // ---------- continuar estudando ----------

    public function test_continuar_estudando_leva_a_primeira_aula_nao_concluida(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula Um', 'Aula Dois', 'Aula Tres']);
        $aluno = $this->aluno($a);
        $this->concluir($aluno, $a, 1);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringContainsString('Continuar estudando', $menu);
        $this->assertStringContainsString('href="/aluno/curso/menu-'.$a->id.'/aula/'.$a->id.'-a2"', $menu);
        $this->assertStringContainsString('Aula Dois', $menu);
    }

    public function test_continuar_estudando_prefere_o_curso_em_tela(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $b = $this->curso('Curso Menu Beta', ['Aula 1']);
        $aluno = $this->aluno($a, [$b->id]);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$b->id)->assertOk()->getContent());

        $this->assertStringContainsString('href="/aluno/curso/menu-'.$b->id.'/aula/'.$b->id.'-a1"', $menu);
    }

    public function test_sem_aula_pendente_nao_ha_botao_de_continuar(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1', 'Aula 2']);
        $aluno = $this->aluno($a);
        $this->concluir($aluno, $a, 1, 2);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringNotContainsString('Continuar estudando', $menu);
    }

    // ---------- avaliações ----------

    public function test_avaliacoes_e_item_do_menu_sob_o_curso_em_tela_e_saiu_da_pagina_do_curso(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $aluno = $this->aluno($a);

        $html = (string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id)->assertOk()->getContent();
        $link = 'href="/aluno/curso/menu-'.$a->id.'/avaliacoes"';

        $this->assertStringContainsString($link, $this->menuLargo($html));
        $this->assertStringContainsString($link, $this->menuEstreito($html), 'No celular o caminho até as avaliações não pode sumir.');

        // Um caminho só: o link não está também na área principal (controle duplicado).
        $this->assertSame(1, preg_match('#<main id="conteudo".*?</main>#s', $html, $principal));
        $this->assertStringNotContainsString('/avaliacoes', $principal[0]);
        $this->assertStringNotContainsString('Avaliações do curso', $html);
    }

    public function test_a_tela_de_avaliacoes_acende_o_item_avaliacoes(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $aluno = $this->aluno($a);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id.'/avaliacoes')->assertOk()->getContent());

        $this->assertMatchesRegularExpression('#<a href="/aluno/curso/menu-'.$a->id.'/avaliacoes"\s+aria-current="page"#', $menu);
    }

    // ---------- rótulos ----------

    public function test_rotulos_novos_e_nenhum_dos_antigos(): void
    {
        $aluno = $this->aluno(null);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        foreach (['Início', 'Descobrir cursos', 'Certificados', 'Ajuda e suporte', 'Perfil'] as $rotulo) {
            $this->assertStringContainsString($rotulo, $menu);
        }
        foreach (['>Painel<', '>Catálogo<', 'Suporte pedagógico', '>Ajuda<', 'Meu curso<'] as $antigo) {
            $this->assertStringNotContainsString($antigo, $menu);
        }
    }

    // ---------- ajuda e suporte ----------

    public function test_ajuda_e_suporte_e_um_item_so_que_abre_nas_duvidas(): void
    {
        $aluno = $this->aluno(null);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());

        $this->assertStringContainsString('href="/aluno/duvidas"', $menu);
        // Um link só (o rótulo aparece mais de uma vez no HTML: visível, `title` e texto do leitor de tela).
        $this->assertSame(1, substr_count($menu, 'href="/aluno/duvidas"'));
        $this->assertStringNotContainsString('href="/aluno/suporte', $menu);
    }

    public function test_dentro_de_uma_aula_ajuda_e_suporte_abre_na_conversa_com_a_aula(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $aluno = $this->aluno($a);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id.'/aula/'.$a->id.'-a1')->assertOk()->getContent());

        $this->assertStringContainsString('href="/aluno/suporte?aula='.$a->id.'-a1"', $menu);
    }

    public function test_as_duas_paginas_ganham_as_abas_e_o_item_do_menu_acende_nas_duas(): void
    {
        $aluno = $this->aluno(null);

        foreach (['/aluno/duvidas' => 'Dúvidas frequentes', '/aluno/suporte' => 'Falar com o gestor'] as $rota => $aba) {
            $html = (string) $this->como($aluno['token'])->get($rota)->assertOk()->getContent();

            $this->assertStringContainsString('aria-label="Ajuda e suporte"', $html);
            $this->assertStringContainsString('href="/aluno/duvidas"', $html);
            $this->assertStringContainsString('href="/aluno/suporte"', $html);
            // A aba ativa se distingue por aria-current, e não só pela cor.
            $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>\s*'.preg_quote($aba, '#').'#u', $html);
            // O item do menu fica aceso nas duas páginas.
            $this->assertMatchesRegularExpression('#<a href="/aluno/duvidas"\s+aria-current="page"[^>]*>.*?Ajuda e suporte#s', $this->menuLargo($html));
        }
    }

    public function test_com_mensagens_desligadas_nao_ha_abas_nem_caminho_para_o_suporte(): void
    {
        config(['features.mensagensDiretas' => false]);
        $aluno = $this->aluno(null);

        $html = (string) $this->como($aluno['token'])->get('/aluno/duvidas')->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Ajuda e suporte"', $html);
        $this->assertStringNotContainsString('href="/aluno/suporte"', $html);
    }

    // ---------- o que o redesenho aprovado continua exigindo ----------

    public function test_cursos_concluidos_continua_submenu_e_o_concluido_em_tela_acha_o_caminho(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $b = $this->curso('Curso Menu Concluido', ['Aula 1']);
        $aluno = $this->aluno($a, [], [$b->id]);

        $menu = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent());
        $this->assertStringContainsString('id="lista-de-concluidos"', $menu);
        $this->assertStringContainsString('Curso Menu Concluido', $menu);

        // Um curso concluído em tela entra na lista como o atual: é nele que moram as avaliações.
        $doConcluido = $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$b->id)->assertOk()->getContent());
        $this->assertStringContainsString('href="/aluno/curso/menu-'.$b->id.'/avaliacoes"', $doConcluido);
    }

    public function test_aulas_do_curso_aparece_uma_unica_vez_por_tela(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1', 'Aula 2']);
        $aluno = $this->aluno($a);

        $naAula = (string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id.'/aula/'.$a->id.'-a1')->assertOk()->getContent();
        $noCurso = (string) $this->como($aluno['token'])->get('/aluno/curso/menu-'.$a->id)->assertOk()->getContent();

        $this->assertSame(1, substr_count($naAula, 'Aulas do curso'));
        $this->assertSame(1, substr_count($noCurso, 'Aulas do curso'));
    }

    // ---------- uma lista só ----------

    public function test_o_menu_largo_e_o_estreito_tem_os_mesmos_itens(): void
    {
        $a = $this->curso('Curso Menu Alfa', ['Aula 1']);
        $aluno = $this->aluno($a);
        $html = (string) $this->como($aluno['token'])->get('/aluno')->assertOk()->getContent();

        $largo = $this->menuLargo($html);
        $estreito = $this->menuEstreito($html);

        $itens = app(MenuDoAluno::class)->itens();
        $this->assertNotEmpty($itens);

        foreach ($itens as $item) {
            $this->assertStringContainsString('href="'.$item['rota'].'"', $largo, 'Falta no menu largo: '.$item['rotulo']);
            $this->assertStringContainsString('href="'.$item['rota'].'"', $estreito, 'Falta no menu estreito: '.$item['rotulo']);
            $this->assertStringContainsString($item['rotulo'], $estreito);
        }

        // E o menu nenhum leva à tela de mensagens, fechada ao aluno.
        $this->assertStringNotContainsString('href="/aluno/mensagens"', $html);
    }

    public function test_a_biblioteca_so_entra_no_menu_com_a_flag_ligada(): void
    {
        $aluno = $this->aluno(null);

        config(['features.bibliotecaDigital' => false]);
        $this->assertStringNotContainsString('/aluno/biblioteca', $this->menuLargo((string) $this->como($aluno['token'])->get('/aluno')->getContent()));

        config(['features.bibliotecaDigital' => true]);
        $html = (string) $this->como($aluno['token'])->get('/aluno')->getContent();
        $this->assertStringContainsString('href="/aluno/biblioteca"', $this->menuLargo($html));
        $this->assertStringContainsString('href="/aluno/biblioteca"', $this->menuEstreito($html));
    }
}
