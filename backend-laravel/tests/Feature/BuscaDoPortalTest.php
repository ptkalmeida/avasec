<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SitePageContentService;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Busca geral do cabeçalho — o primeiro componente Livewire da migração com
 * resultado vindo de dados (fase M2 do plano 16).
 *
 * A pergunta que este arquivo responde não é "o painel abre?", e sim **de onde
 * vêm os resultados**. A busca do React procura na vitrine fixa do portal e nas
 * notícias do admin — nunca na tabela `Course`. Se alguém apontar esta busca
 * para o banco, ela passará a oferecer ao público os registros de teste que
 * existem lá, e as duas tecnologias darão respostas diferentes para a mesma
 * pessoa na mesma sessão.
 */
final class BuscaDoPortalTest extends TestCase
{
    public function test_abaixo_de_dois_caracteres_nao_busca_nada(): void
    {
        Livewire::test('publico.busca-do-portal')
            ->set('termo', 'a')
            ->assertSet('cursos', [])
            ->assertSet('noticias', []);
    }

    public function test_encontra_curso_da_vitrine_pelo_titulo(): void
    {
        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');
        $primeiro = $vitrine[0];

        Livewire::test('publico.busca-do-portal')
            ->set('termo', mb_substr($primeiro['title'], 0, 10))
            ->assertSee($primeiro['title']);
    }

    /**
     * A trava que justifica este arquivo: o resultado sai da vitrine, não do
     * banco. Conferido pelo avesso — tudo que a busca devolve tem de existir na
     * vitrine.
     */
    public function test_so_devolve_curso_que_esta_na_vitrine(): void
    {
        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');
        $titulosDaVitrine = array_column($vitrine, 'title');

        /** @var list<array{title:string,category:string}> $achados */
        $achados = Livewire::test('publico.busca-do-portal')
            ->set('termo', 'cu')
            ->get('cursos');

        $this->assertNotEmpty($achados, 'A busca por "cu" deveria achar algo na vitrine.');

        foreach ($achados as $achado) {
            $this->assertContains(
                $achado['title'],
                $titulosDaVitrine,
                'A busca devolveu um curso que não está na vitrine pública — ela foi apontada para o banco?',
            );
        }
    }

    public function test_limita_o_painel_a_cinco_por_grupo(): void
    {
        /** @var list<array{title:string,category:string}> $achados */
        $achados = Livewire::test('publico.busca-do-portal')
            ->set('termo', 'a')   // curto demais: não busca
            ->set('termo', 'de')  // casa com quase tudo
            ->get('cursos');

        $this->assertLessThanOrEqual(5, count($achados));
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
            ->set('termo', mb_substr((string) $primeira['title'], 0, 8))
            ->assertSee($primeira['title']);
    }
}
