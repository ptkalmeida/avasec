<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ConteudoDaAula;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Imagem da aula que abre ampliada ao clicar (plano 20).
 *
 * O componente é um só (`x-aluno.conteudo-da-aula`) e serve a aula do aluno, o editor
 * do instrutor e a prévia: o que se prende aqui vale nos três. O que só o navegador
 * responde — abrir de verdade, o `Esc` fechar — está no e2e (`aula.spec.ts`).
 */
final class ImagemAmpliavelDaAulaTest extends TestCase
{
    /** @param list<array<string, mixed>> $blocos */
    private function renderiza(array $blocos): string
    {
        return Blade::render('<x-aluno.conteudo-da-aula :blocos="$blocos" />', ['blocos' => $blocos]);
    }

    /** Renderiza o texto como o autor o escreve, passando pelo mesmo leitor da aula. */
    private function renderizaTexto(string $texto): string
    {
        return $this->renderiza(ConteudoDaAula::analisar($texto)['blocos']);
    }

    public function test_a_imagem_vira_link_para_o_arquivo_e_ganha_a_janela_ampliada(): void
    {
        $html = $this->renderizaTexto('![Mesmo registro](/uploads/foto.png)');

        // O link é o que sobrevive sem JavaScript: abre o arquivo em outra aba.
        $this->assertStringContainsString('href="/uploads/foto.png"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('aria-label="Ampliar imagem: Mesmo registro"', $html);
        $this->assertStringContainsString('cursor-zoom-in', $html);

        // A janela é o <dialog> nativo, com a mesma URL e um botão para fechar.
        $this->assertStringContainsString('<dialog', $html);
        $this->assertSame(2, substr_count($html, 'src="/uploads/foto.png"'), 'miniatura e ampliada usam a mesma URL');
        $this->assertStringContainsString('showModal()', $html);
        $this->assertStringContainsString('method="dialog"', $html);
        $this->assertStringContainsString('Fechar', $html);
    }

    public function test_imagem_decorativa_sem_alt_continua_com_nome_acessivel_no_link(): void
    {
        $html = $this->renderizaTexto('![](/uploads/enfeite.png)');

        // `alt` vazio é decisão do autor (imagem decorativa) e é mantido vazio...
        $this->assertStringContainsString('alt=""', $html);
        // ...mas um link sem nome é inalcançável para quem usa leitor de tela.
        $this->assertStringContainsString('aria-label="Ampliar imagem"', $html);
        $this->assertStringNotContainsString('Ampliar imagem:', $html);
    }

    public function test_a_legenda_aparece_na_miniatura_e_na_janela_ampliada(): void
    {
        // No formato do autor a legenda vem ANTES da imagem a que pertence.
        $html = $this->renderizaTexto("Figura 1 - Espetáculo no palco\n![Foto](/uploads/foto.png)");

        $this->assertSame(2, substr_count($html, 'Figura 1 - Espetáculo no palco'));
    }

    public function test_cada_imagem_tem_a_sua_propria_janela(): void
    {
        $html = $this->renderizaTexto("![Um](/uploads/um.png)\n\n![Dois](/uploads/dois.png)");

        $this->assertSame(2, substr_count($html, '<dialog'));
        $this->assertSame(2, substr_count($html, 'x-data'));
        // `x-ref` é local ao `x-data` de cada figura: clicar na segunda não abre a primeira.
        $this->assertSame(2, substr_count($html, 'x-ref="ampliada"'));
    }

    public function test_sem_imagem_nao_ha_janela_nem_cursor_de_zoom(): void
    {
        $html = $this->renderizaTexto("## Título\n\nSó texto, sem imagem.");

        $this->assertStringNotContainsString('<dialog', $html);
        $this->assertStringNotContainsString('cursor-zoom-in', $html);
        $this->assertStringNotContainsString('x-data', $html);
    }

    /** A regra do componente: nada do que o autor escreve vira marcação. */
    public function test_texto_alternativo_malicioso_sai_escapado(): void
    {
        $html = $this->renderiza([[
            'tipo' => 'imagem',
            'url' => '/uploads/foto.png',
            'alt' => '"><script>alert(1)</script>',
            'legenda' => '<img src=x onerror=alert(2)>',
        ]]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_video_e_codigo_nao_ganham_a_janela_de_imagem(): void
    {
        $html = $this->renderizaTexto("```php\necho 1;\n```");

        $this->assertStringNotContainsString('<dialog', $html);
    }
}
