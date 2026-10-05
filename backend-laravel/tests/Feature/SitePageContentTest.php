<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Conteúdo editável das páginas públicas do portal — leitura pública (o
 * visitante anônimo monta o site com isso), escrita só do Admin Superior.
 * Requer MySQL de dev populado.
 */
final class SitePageContentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    public function test_anonymous_visitor_reads_all_pages_with_defaults(): void
    {
        $response = $this->getJson('/api/site-content')->assertOk();

        // Sem nenhuma linha salva, o conteúdo vem dos defaults hardcoded.
        $response->assertJsonPath('pages.orientacoes.title', 'Orientações Gerais');
        $response->assertJsonCount(4, 'pages.orientacoes.items');
        $response->assertJsonPath('pages.duvidas.updatedAt', null);

        // O schema acompanha a resposta para a tela do admin montar os rótulos.
        $response->assertJsonPath('schema.duvidas.label', 'Dúvidas Frequentes');
        $response->assertJsonPath('schema.duvidas.item.0.label', 'Pergunta');
    }

    public function test_anonymous_visitor_reads_a_single_page(): void
    {
        $this->getJson('/api/site-content/calendario')
            ->assertOk()
            ->assertJsonPath('pageKey', 'calendario')
            ->assertJsonCount(3, 'items');
    }

    public function test_unknown_page_key_is_404(): void
    {
        $this->getJson('/api/site-content/pagina-inexistente')
            ->assertStatus(404)
            ->assertJsonPath('error', true);
    }

    public function test_admin_updates_header_and_items(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/site-content/orientacoes', [
                'eyebrow' => 'Manual Atualizado',
                'title' => 'Orientações do Estudante',
                'description' => 'Novo texto de abertura.',
                'items' => [
                    ['id' => 'diretriz-1', 'title' => 'Primeira regra', 'description' => 'Descrição da primeira regra.'],
                    ['id' => 'diretriz-2', 'title' => 'Segunda regra', 'description' => 'Descrição da segunda regra.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Orientações do Estudante')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.1.title', 'Segunda regra');

        // A escrita persiste e continua visível para o visitante anônimo.
        $this->flushHeaders();
        $this->getJson('/api/site-content/orientacoes')
            ->assertOk()
            ->assertJsonPath('title', 'Orientações do Estudante')
            ->assertJsonCount(2, 'items');
    }

    public function test_update_stamps_who_edited(): void
    {
        $admin = $this->staffToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/site-content/duvidas', [
                'items' => [['id' => 'faq-1', 'question' => 'Pergunta?', 'answer' => 'Resposta.']],
            ])
            ->assertOk();

        $this->assertNotNull($response->json('updatedAt'));
        $this->assertNotNull($response->json('updatedByUserId'));
    }

    public function test_unknown_fields_are_discarded_and_long_text_is_truncated(): void
    {
        $admin = $this->staffToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/site-content/duvidas', [
                'title' => str_repeat('A', 500),
                'campoInventado' => 'deveria ser ignorado',
                'items' => [
                    [
                        'id' => 'faq-1',
                        'question' => 'Pergunta válida?',
                        'answer' => 'Resposta.',
                        'chaveDesconhecida' => 'ignorada',
                    ],
                ],
            ])
            ->assertOk();

        // Campo curto é truncado no limite do schema (191), não rejeitado.
        $this->assertSame(191, mb_strlen((string) $response->json('title')));
        $this->assertNull($response->json('campoInventado'));
        $this->assertNull($response->json('items.0.chaveDesconhecida'));
    }

    public function test_completely_empty_items_are_dropped(): void
    {
        $admin = $this->staffToken('admin');

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/site-content/duvidas', [
                'items' => [
                    ['id' => 'faq-1', 'question' => 'Só esta vale', 'answer' => 'Resposta.'],
                    ['id' => 'faq-2', 'question' => '', 'answer' => ''],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_item_count_is_capped_by_schema(): void
    {
        $admin = $this->staffToken('admin');

        // 'o-ava' aceita no máximo 9 destaques.
        $items = [];
        for ($i = 1; $i <= 15; $i++) {
            $items[] = ['id' => "ava-{$i}", 'title' => "Destaque {$i}", 'description' => 'Texto.'];
        }

        $this->withHeader('Authorization', "Bearer {$admin}")
            ->putJson('/api/site-content/o-ava', ['items' => $items])
            ->assertOk()
            ->assertJsonCount(9, 'items');
    }

    public function test_student_cannot_write_site_content(): void
    {
        $student = $this->makeStudent('sitecontent');

        $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->putJson('/api/site-content/duvidas', ['title' => 'Invasão'])
            ->assertStatus(403);
    }

    public function test_instructor_cannot_write_site_content(): void
    {
        $instructor = $this->staffToken('instructor');

        $this->withHeader('Authorization', "Bearer {$instructor}")
            ->putJson('/api/site-content/duvidas', ['title' => 'Invasão'])
            ->assertStatus(403);
    }

    public function test_anonymous_cannot_write_site_content(): void
    {
        $this->putJson('/api/site-content/duvidas', ['title' => 'Invasão'])
            ->assertStatus(401);
    }
}
