<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Slug de curso persistido (ADR 13): o endereço passa a dizer o nome do curso.
 *
 * O que se protege aqui é a promessa que justificou a mudança de schema: link
 * salvo NÃO morre. Se estes testes caírem, a alternativa barata (derivar o slug
 * do título na hora de montar o link) volta a ser tão boa quanto esta, porque
 * teria o mesmo defeito sem custar migration.
 */
final class CursoSlugPersistidoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** @return array<string, mixed> */
    private function criarCurso(string $titulo, string $token): array
    {
        $resposta = $this->withHeaders($this->auth($token))->postJson('/api/courses', [
            'title' => $titulo,
            'description' => 'Curso criado por teste.',
            'category' => 'Testes',
            'thumbnail' => 'https://exemplo.test/t.png',
        ])->assertStatus(201);
        $this->flushHeaders();

        /** @var array<string, mixed> $corpo */
        $corpo = $resposta->json();

        return $corpo;
    }

    public function test_curso_novo_nasce_com_slug_derivado_do_titulo(): void
    {
        $curso = $this->criarCurso('Robótica Educacional & Arduino', $this->staffToken('admin'));

        $this->assertSame('robotica-educacional-arduino', $curso['slug']);
    }

    public function test_o_cliente_nao_escolhe_o_proprio_slug(): void
    {
        /*
         * Slug é identidade de endereço. Aceitá-lo do JSON deixaria quem cria um
         * curso escolher um slug que imita outro, ou tomar de antemão o slug de
         * um curso que ainda vai existir. É a mesma razão pela qual
         * `instructorName` é derivado e não aceito do corpo da requisição.
         */
        $token = $this->staffToken('admin');
        $resposta = $this->withHeaders($this->auth($token))->postJson('/api/courses', [
            'title' => 'Curso Com Slug Forjado',
            'slug' => 'ux-ui-design-interfaces-de-alta-performance',
            'description' => 'Descricao suficientemente longa.',
            'category' => 'Testes',
            'thumbnail' => 'https://exemplo.test/t.png',
        ])->assertStatus(201);
        $this->flushHeaders();

        $this->assertSame('curso-com-slug-forjado', $resposta->json('slug'));
    }

    public function test_dois_cursos_com_o_mesmo_titulo_recebem_slugs_diferentes(): void
    {
        $token = $this->staffToken('admin');
        $a = $this->criarCurso('Oficina de Leitura', $token);
        $b = $this->criarCurso('Oficina de Leitura', $token);

        $this->assertSame('oficina-de-leitura', $a['slug']);
        $this->assertSame('oficina-de-leitura-2', $b['slug']);
    }

    public function test_renomear_gera_slug_novo_e_o_antigo_continua_abrindo_o_curso(): void
    {
        // A promessa central da ADR 13.
        $token = $this->staffToken('admin');
        $curso = $this->criarCurso('Introducao a Estatistica', $token);
        $id = (string) $curso['id'];
        $slugAntigo = (string) $curso['slug'];

        $atualizado = $this->withHeaders($this->auth($token))
            ->putJson("/api/courses/{$id}", ['title' => 'Estatistica Aplicada a Educacao'])
            ->assertOk();
        $this->flushHeaders();

        $slugNovo = (string) $atualizado->json('slug');
        $this->assertSame('estatistica-aplicada-a-educacao', $slugNovo);
        $this->assertNotSame($slugAntigo, $slugNovo);

        // O endereço de hoje é canônico.
        $this->getJson("/api/courses/resolve/{$slugNovo}")
            ->assertOk()
            ->assertJson(['id' => $id, 'slug' => $slugNovo, 'canonico' => true]);

        // O link salvo antes do rename abre o MESMO curso, e diz qual é o de hoje.
        $this->getJson("/api/courses/resolve/{$slugAntigo}")
            ->assertOk()
            ->assertJson(['id' => $id, 'slug' => $slugNovo, 'canonico' => false]);
    }

    public function test_link_com_o_id_antigo_continua_funcionando(): void
    {
        /*
         * Todo link que circula hoje tem a forma `/aluno/curso/course-1`. Uma
         * melhoria de endereço que transforma esses links em 404 é uma regressão
         * disfarçada de melhoria.
         */
        $this->getJson('/api/courses/resolve/course-1')
            ->assertOk()
            ->assertJson(['id' => 'course-1', 'canonico' => false])
            ->assertJsonPath('slug', 'ux-ui-design-interfaces-de-alta-performance');
    }

    public function test_slug_desconhecido_da_404(): void
    {
        $this->getJson('/api/courses/resolve/curso-que-nunca-existiu')->assertNotFound();
    }

    public function test_rename_de_ida_e_volta_nao_estoura_o_historico(): void
    {
        /*
         * Corrigir o título e desfazer a correção aposenta o mesmo slug duas
         * vezes. A segunda não pode estourar a chave primária do histórico e
         * derrubar o salvamento do formulário.
         */
        $token = $this->staffToken('admin');
        $curso = $this->criarCurso('Nome Original Do Curso', $token);
        $id = (string) $curso['id'];

        $this->withHeaders($this->auth($token))
            ->putJson("/api/courses/{$id}", ['title' => 'Nome Trocado'])->assertOk();
        $this->flushHeaders();
        $this->withHeaders($this->auth($token))
            ->putJson("/api/courses/{$id}", ['title' => 'Nome Original Do Curso'])->assertOk();
        $this->flushHeaders();

        /*
         * Voltar ao título original NÃO reaproveita o slug original: ele está
         * aposentado e ainda resolve. O curso recebe `-2`, e nenhum link mente.
         * O endereço fica um pouco feio; a alternativa era um link salvo abrir
         * outro curso.
         */
        $atual = DB::table('Course')->where('id', $id)->value('slug');
        $this->assertSame('nome-original-do-curso-2', $atual);

        foreach (['nome-original-do-curso', 'nome-trocado'] as $aposentado) {
            $this->getJson("/api/courses/resolve/{$aposentado}")
                ->assertOk()
                ->assertJson(['id' => $id, 'canonico' => false]);
        }
    }

    public function test_salvar_sem_mexer_no_titulo_nao_troca_o_endereco(): void
    {
        $token = $this->staffToken('admin');
        $curso = $this->criarCurso('Curso Que Nao Muda De Nome', $token);
        $id = (string) $curso['id'];
        $slug = (string) $curso['slug'];

        $this->withHeaders($this->auth($token))
            ->putJson("/api/courses/{$id}", ['title' => 'Curso Que Nao Muda De Nome', 'category' => 'Outra'])
            ->assertOk()
            ->assertJsonPath('slug', $slug);
        $this->flushHeaders();

        // Histórico limpo: salvar o formulário não aposenta endereço.
        $this->assertSame(0, DB::table('CourseSlugHistory')->where('courseId', $id)->count());
    }

    public function test_visitante_nao_confirma_a_existencia_de_curso_em_rascunho(): void
    {
        /*
         * `resolve` é público como o catálogo, e por isso passa pela mesma escada
         * de audiência. Sem isso ela seria o único jeito de um anônimo confirmar
         * que um curso não publicado existe.
         */
        $token = $this->staffToken('admin');
        $curso = $this->criarCurso('Curso Em Elaboracao Sigilosa', $token);
        $id = (string) $curso['id'];
        $slug = (string) $curso['slug'];

        DB::table('Course')->where('id', $id)->update(['status' => Visibilidade::RASCUNHO]);

        $this->getJson("/api/courses/resolve/{$slug}")->assertNotFound();
        $this->getJson("/api/courses/resolve/{$slug}", $this->auth($token))
            ->assertOk()
            ->assertJson(['id' => $id, 'canonico' => true]);
    }
}
