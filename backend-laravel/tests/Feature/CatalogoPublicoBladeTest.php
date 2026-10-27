<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Services\CatalogoPublico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Catálogo público em Blade — tela 2 do redesenho (plano 18, fase M2).
 *
 * Protege duas coisas. As decisões da skill que são fáceis de desfazer sem
 * querer: o título e a **ausência** dos controles removidos (teste de ausência
 * é incomum, e aqui é o principal — nada além dele impede alguém de trazer a
 * busca de volta por reflexo). E a regra de que a vitrine mostra só os cursos
 * ativos agora para serem cursados, vindos da plataforma.
 */
final class CatalogoPublicoBladeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_responde_em_blade_e_nao_pelo_react(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertOk();
        // A âncora onde o React se monta não existe numa tela Blade.
        $resposta->assertDontSee('id="root"', escape: false);
    }

    public function test_titulo_e_o_aprovado_pela_skill(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertSee('Cursos em destaque', escape: false);
        $resposta->assertDontSee('Cursos Disponíveis', escape: false);
    }

    public function test_mostra_o_curso_ativo_da_plataforma(): void
    {
        $this->curso('cat-ativo', 'Oficina de Teatro Ativa');

        $this->get('/cursos')->assertSee('Oficina de Teatro Ativa', escape: false);
    }

    public function test_nao_mostra_curso_inativado(): void
    {
        $curso = $this->curso('cat-inativo', 'Oficina Inativada');
        $curso->inativar(null, 'teste');

        $this->get('/cursos')->assertDontSee('Oficina Inativada', escape: false);
    }

    public function test_nao_mostra_curso_em_rascunho_nem_restrito(): void
    {
        $this->curso('cat-rascunho', 'Oficina em Rascunho', ['status' => 3]);
        $this->curso('cat-restrito', 'Oficina Restrita', ['status' => 2]);

        $resposta = $this->get('/cursos');

        $resposta->assertDontSee('Oficina em Rascunho', escape: false);
        $resposta->assertDontSee('Oficina Restrita', escape: false);
    }

    public function test_nao_mostra_curso_com_vigencia_vencida(): void
    {
        $this->curso('cat-vencido', 'Oficina Vencida', ['vigencia_ate' => '2020-01-01', 'vencimento_contrato_texto' => '2020-01-01']);

        $this->get('/cursos')->assertDontSee('Oficina Vencida', escape: false);
    }

    public function test_curso_sem_prazo_de_vigencia_aparece(): void
    {
        $this->curso('cat-permanente', 'Oficina Permanente', ['vigencia_ate' => null, 'vencimento_contrato_texto' => null]);

        $this->get('/cursos')->assertSee('Oficina Permanente', escape: false);
    }

    public function test_so_mostra_cursos_que_existem_na_plataforma(): void
    {
        $titulosDaPlataforma = array_column(app(CatalogoPublico::class)->cursaveis(), 'titulo');
        $html = (string) $this->get('/cursos')->getContent();

        // A vitrine fixa do React mostrava cursos que nunca existiram no banco.
        foreach (['Inteligência Artificial e Cultura 2ª Oferta', 'Produção Audiovisual 2ª Oferta'] as $fantasma) {
            if (! in_array($fantasma, $titulosDaPlataforma, true)) {
                $this->assertStringNotContainsString(e($fantasma), $html);
            }
        }
    }

    public function test_mostra_os_quatro_mais_novos_do_mais_novo_ao_mais_antigo(): void
    {
        foreach (range(1, 6) as $n) {
            $this->curso("lote-{$n}", "Curso de Lote {$n}");
        }

        $html = (string) $this->get('/cursos')->getContent();

        $this->assertSame(4, substr_count($html, 'Inscrever-se</span>'));
        // Os seis foram criados em sequência: o 6 é o mais novo e abre a lista.
        foreach ([6, 5, 4, 3] as $n) {
            $this->assertStringContainsString("Curso de Lote {$n}", $html);
        }
        foreach ([2, 1] as $n) {
            $this->assertStringNotContainsString("Curso de Lote {$n}", $html);
        }
        $this->assertLessThan(strpos($html, 'Curso de Lote 5'), strpos($html, 'Curso de Lote 6'));
        $this->assertLessThan(strpos($html, 'Curso de Lote 4'), strpos($html, 'Curso de Lote 5'));
    }

    public function test_curso_sem_capa_ganha_bloco_da_identidade_e_nao_imagem(): void
    {
        $this->curso('sem-capa', 'Oficina Sem Capa', ['miniatura' => '', 'imagem_capa' => '']);
        $html = (string) $this->get('/cursos')->getContent();

        $this->assertStringContainsString('Oficina Sem Capa', $html);
        $this->assertStringContainsString('from-ava-marinho to-ava-acao', $html);
    }

    public function test_curso_sem_instrutor_nao_inventa_nome(): void
    {
        $this->curso('cat-sem-instrutor', 'Oficina Sem Instrutor', ['nome_instrutor' => '']);
        $html = (string) $this->get('/cursos')->getContent();

        $this->assertStringContainsString('Oficina Sem Instrutor', $html);
        $this->assertStringNotContainsString('Tutorado por: Prof. </p>', $html);
    }

    /**
     * A skill removeu a busca e os filtros do catálogo e diz, com todas as
     * letras, para não reintroduzi-los sem nova solicitação.
     */
    public function test_nao_tem_busca_propria_nem_filtro_por_categoria(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertDontSee('Buscar curso pelo nome', escape: false);
        $resposta->assertDontSee('Filtrar por categoria', escape: false);
        $resposta->assertDontSee('Limpar Filtros', escape: false);
        // O texto de abertura não pode voltar a prometer filtros que não existem.
        $resposta->assertDontSee('Use os filtros', escape: false);
    }

    public function test_tem_o_botao_veja_mais_cursos_apontando_para_o_login(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertSee('Veja mais cursos', escape: false);
        $resposta->assertSee('href="/entrar"', escape: false);
    }

    /**
     * A tela não pode afirmar uma ordenação que o schema não sustenta: não há
     * data de publicação. A skill prevê o caso e manda não afirmar.
     */
    public function test_nao_afirma_que_os_cursos_sao_os_mais_recentes(): void
    {
        $resposta = $this->get('/cursos');

        $resposta->assertDontSee('mais recentes', escape: false);
        $resposta->assertDontSee('Mais recentes', escape: false);
    }

    private int $sequencia = 0;

    /**
     * O id imita o do painel (`course-<ms>`), com número maior que o de qualquer
     * curso real: o teste cria cursos "novos", que a vitrine põe na frente.
     *
     * @param  array<string, mixed>  $extra
     */
    private function curso(string $rotulo, string $titulo, array $extra = []): Curso
    {
        $id = 'course-'.(9_000_000_000_000 + ++$this->sequencia);

        $curso = new Curso;
        $curso->forceFill(array_merge([
            'id' => $id,
            'titulo' => $titulo,
            'slug' => $rotulo.'-'.$id,
            'categoria' => 'Teste',
            'descricao' => 'Descrição de '.$titulo,
            'nome_instrutor' => 'Instrutor de Teste',
            'miniatura' => 'https://example.test/capa.jpg',
            'status' => 1,
            'vigencia_ate' => '2099-12-31',
            'vencimento_contrato_texto' => '2099-12-31',
        ], $extra))->save();

        return $curso;
    }
}
