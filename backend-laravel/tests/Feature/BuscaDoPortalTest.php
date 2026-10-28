<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Services\CatalogoPublico;
use App\Services\SitePageContentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Busca geral do cabeçalho — o primeiro componente Livewire da migração com
 * resultado vindo de dados (fase M2 do plano 16).
 *
 * A pergunta que este arquivo responde não é "o painel abre?", e sim **de onde
 * vêm os resultados**: dos cursos ativos agora para serem cursados (os mesmos
 * da tela `/cursos`) e das notícias do admin. Se a busca oferecesse curso
 * inativado, em rascunho ou vencido, ela levaria o público a algo que ele não
 * consegue cursar.
 */
final class BuscaDoPortalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_abaixo_de_dois_caracteres_nao_busca_nada(): void
    {
        Livewire::test('publico.busca-do-portal')
            ->set('termo', 'a')
            ->assertSet('cursos', [])
            ->assertSet('noticias', []);
    }

    public function test_encontra_curso_ativo_pelo_titulo(): void
    {
        $this->curso('busca-ativo', 'Zzyx Oficina de Cerâmica');

        Livewire::test('publico.busca-do-portal')
            ->set('termo', 'Zzyx Oficina')
            ->assertSee('Zzyx Oficina de Cerâmica');
    }

    public function test_nao_encontra_curso_inativado_em_rascunho_ou_vencido(): void
    {
        $this->curso('busca-inativo', 'Zzyx Inativado')->inativar(null, 'teste');
        $this->curso('busca-rascunho', 'Zzyx Rascunho', ['status' => 3]);
        $this->curso('busca-vencido', 'Zzyx Vencido', ['vigencia_ate' => '2020-01-01', 'vencimento_contrato_texto' => '2020-01-01']);

        Livewire::test('publico.busca-do-portal')
            ->set('termo', 'Zzyx')
            ->assertSet('cursos', []);
    }

    /**
     * Conferido pelo avesso: tudo que a busca devolve tem de estar entre os
     * cursos cursáveis da plataforma.
     */
    public function test_so_devolve_curso_cursavel(): void
    {
        $titulosCursaveis = array_column(app(CatalogoPublico::class)->cursaveis(), 'titulo');

        /** @var list<array{title:string,category:string}> $achados */
        $achados = Livewire::test('publico.busca-do-portal')
            ->set('termo', 'de')
            ->get('cursos');

        foreach ($achados as $achado) {
            $this->assertContains($achado['titulo'], $titulosCursaveis);
        }
    }

    public function test_limita_o_painel_a_cinco_por_grupo(): void
    {
        foreach (range(1, 7) as $n) {
            $this->curso("busca-lote-{$n}", "Zzyx Lote {$n}");
        }

        /** @var list<array{title:string,category:string}> $achados */
        $achados = Livewire::test('publico.busca-do-portal')
            ->set('termo', 'Zzyx Lote')
            ->get('cursos');

        $this->assertCount(5, $achados);
    }

    public function test_limpar_fecha_o_painel(): void
    {
        Livewire::test('publico.busca-do-portal')
            ->set('termo', 'cu')
            ->call('limpar')
            ->assertSet('termo', '')
            ->assertSet('cursos', [])
            ->assertSet('noticias', []);
    }

    public function test_encontra_noticia_pelo_titulo(): void
    {
        // Pergunta ao serviço em vez de fixar o texto: o admin edita a notícia,
        // e um texto copiado aqui passaria a falhar sem nada estar errado.
        $primeira = app(SitePageContentService::class)->get('noticias')['items'][0] ?? null;
        $this->assertIsArray($primeira);

        Livewire::test('publico.busca-do-portal')
            ->set('termo', mb_substr((string) $primeira['titulo'], 0, 8))
            ->assertSee($primeira['titulo']);
    }

    /** @param array<string, mixed> $extra */
    private function curso(string $id, string $titulo, array $extra = []): Curso
    {
        $curso = new Curso;
        $curso->forceFill(array_merge([
            'id' => $id,
            'titulo' => $titulo,
            'slug' => $id,
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
