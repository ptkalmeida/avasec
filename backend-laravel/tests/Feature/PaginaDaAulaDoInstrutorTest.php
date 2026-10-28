<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}/aulas/{id}` e `/aulas/nova` — a página de uma aula e a aula
 * nova, em Blade.
 *
 * O que prendem: que cada zona grava SÓ a aula aberta (o React reenviava o curso
 * inteiro), que o erro reabre a zona com o que a pessoa digitou, que o curso de
 * outro instrutor responde 404 na leitura e nas escritas, e que a aula nova entra
 * no fim da grade.
 */
final class PaginaDaAulaDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id,
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
        ]);

        return ['id' => $id, 'nome' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    /** @return array{0:Curso,1:list<Aula>} */
    private function cursoComAulas(string $instrutor_id, string $titulo = 'Curso Da Pagina Da Aula'): array
    {
        $curso = Curso::query()->create([
            'id' => 'course-pagina-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $instrutor_id,
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        $aulas = [];
        for ($i = 1; $i <= 3; $i++) {
            $aulas[] = Aula::query()->create([
                'id' => 'lesson-pagina-'.$curso->id.'-'.$i,
                'curso_id' => $curso->id,
                'titulo' => "Aula numero {$i}",
                'duracao' => '30 min',
                'conteudo' => "## Seção {$i}\n\nTexto da aula {$i}.",
                'ordem' => $i,
            ]);
        }

        return [$curso->refresh(), $aulas];
    }

    private function pagina(Curso $curso, Aula $aula, string $resto = ''): string
    {
        return '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id.$resto;
    }

    // ---- acesso ---------------------------------------------------------------

    public function test_visitante_sem_sessao_vai_para_o_login_em_toda_rota_da_aula(): void
    {
        foreach (['/inst/curso/x/aulas/nova', '/inst/curso/x/aulas/y'] as $caminho) {
            $this->get($caminho)->assertRedirect('/entrar');
        }
        foreach (['/inst/curso/x/aulas', '/inst/curso/x/aulas/y/identificacao', '/inst/curso/x/aulas/y/video'] as $caminho) {
            $this->post($caminho)->assertRedirect('/entrar');
        }
    }

    public function test_curso_de_outro_instrutor_responde_404_na_leitura_e_nas_escritas(): void
    {
        $eu = $this->instrutor('Gestor Um Pagina');
        $outro = $this->instrutor('Gestor Dois Pagina');
        [$alheio, $aulas] = $this->cursoComAulas($outro['id'], 'Curso Alheio Da Pagina');

        $this->como($eu['token'])->get($this->pagina($alheio, $aulas[0]))->assertNotFound();
        $this->como($eu['token'])->get('/inst/curso/'.$alheio->slug.'/aulas/nova')->assertNotFound();
        $this->como($eu['token'])->post($this->pagina($alheio, $aulas[0], '/identificacao'), ['titulo' => 'Invasor', 'carga' => '1 min'])->assertNotFound();
        $this->como($eu['token'])->post($this->pagina($alheio, $aulas[0], '/video'), ['video' => ''])->assertNotFound();
        $this->como($eu['token'])->post('/inst/curso/'.$alheio->slug.'/aulas', ['titulo' => 'Invasora', 'carga' => '1 min', 'texto' => 'x'])->assertNotFound();

        $this->assertSame(3, Aula::query()->where('curso_id', $alheio->id)->count());
        $this->assertSame('Aula numero 1', Aula::query()->findOrFail($aulas[0]->id)->titulo);
    }

    public function test_com_o_modulo_de_aulas_desligado_nada_responde(): void
    {
        $eu = $this->instrutor('Gestor Desligado');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        config(['features.modulosAulas' => false]);

        $this->como($eu['token'])->get($this->pagina($curso, $aulas[0]))->assertNotFound();
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/aulas/nova')->assertNotFound();
        $this->como($eu['token'])->post($this->pagina($curso, $aulas[0], '/identificacao'), ['titulo' => 'x', 'carga' => '1 min'])->assertNotFound();
    }

    // ---- a página ---------------------------------------------------------------

    public function test_mostra_a_aula_com_as_quatro_zonas_e_o_texto_como_o_aluno_ve(): void
    {
        $eu = $this->instrutor('Gestor Pagina');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $resposta = $this->como($eu['token'])->get($this->pagina($curso, $aulas[1]));

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Aula numero 2');
        $resposta->assertSee('aula 2 de 3');
        foreach (['Identificação da aula', 'Vídeo principal da aula', 'Material didático', 'Material de apoio (0)'] as $zona) {
            $resposta->assertSee($zona);
        }
        $resposta->assertSee('Texto da aula 2.');
        $resposta->assertSee('Aula sem vídeo');
        // Fechada, a zona não mostra formulário.
        $resposta->assertDontSee('Salvar identificação');
    }

    public function test_aula_que_nao_existe_volta_para_a_grade(): void
    {
        $eu = $this->instrutor('Gestor Sem Aula');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/aulas/lesson-que-nao-existe')
            ->assertRedirect('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->assertSessionHasErrors('grade');
    }

    public function test_aula_ja_retirada_nao_abre(): void
    {
        $eu = $this->instrutor('Gestor Retirada');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        $aulas[0]->inativar($eu['id'], 'teste');

        $this->como($eu['token'])->get($this->pagina($curso, $aulas[0]))
            ->assertRedirect('/inst/curso/'.$curso->slug.'/grade-curricular');
    }

    public function test_o_endereco_pelo_id_do_curso_vai_para_o_slug(): void
    {
        $eu = $this->instrutor('Gestor Ref');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->id.'/aulas/'.$aulas[0]->id)
            ->assertRedirect('/inst/curso/'.$curso->slug.'/aulas/'.$aulas[0]->id);
    }

    // ---- identificação ----------------------------------------------------------

    public function test_a_zona_abre_pelo_endereco_e_salva_so_a_identificacao(): void
    {
        $eu = $this->instrutor('Gestor Identificacao');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get($this->pagina($curso, $aulas[0]).'?editar=identificacao')
            ->assertOk()->assertSee('Salvar identificação')->assertSee('Fechar');

        $this->como($eu['token'])
            ->post($this->pagina($curso, $aulas[0], '/identificacao'), ['titulo' => 'Título corrigido', 'carga' => '45 min'])
            ->assertRedirect($this->pagina($curso, $aulas[0]).'#identificacao')
            ->assertSessionHas('avisoDoCurso');

        $a = Aula::query()->findOrFail($aulas[0]->id);
        $this->assertSame('Título corrigido', $a->titulo);
        $this->assertSame('45 min', $a->duracao);
        $this->assertSame("## Seção 1\n\nTexto da aula 1.", $a->conteudo);
        $this->assertSame(1, $a->ordem);

        // As outras aulas não foram tocadas.
        $b = Aula::query()->findOrFail($aulas[1]->id);
        $this->assertSame('Aula numero 2', $b->titulo);
        $this->assertSame('30 min', $b->duracao);
    }

    public function test_titulo_vazio_reabre_a_zona_com_o_erro_e_o_que_foi_digitado(): void
    {
        $eu = $this->instrutor('Gestor Titulo Vazio');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->from($this->pagina($curso, $aulas[0]))
            ->post($this->pagina($curso, $aulas[0], '/identificacao'), ['titulo' => '   ', 'carga' => '99 min'])
            ->assertRedirect($this->pagina($curso, $aulas[0]).'?editar=identificacao#identificacao')
            ->assertSessionHasErrors('identificacao')
            ->assertSessionHasInput('carga', '99 min');

        $this->assertSame('Aula numero 1', Aula::query()->findOrFail($aulas[0]->id)->titulo);
    }

    // ---- vídeo ------------------------------------------------------------------

    public function test_salva_o_video_na_forma_canonica_e_mostra_o_player(): void
    {
        $eu = $this->instrutor('Gestor Video');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post($this->pagina($curso, $aulas[0], '/video'), ['video' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertRedirect($this->pagina($curso, $aulas[0]).'#video');

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', Aula::query()->findOrFail($aulas[0]->id)->url_video);

        $this->como($eu['token'])->get($this->pagina($curso, $aulas[0]))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('Aula sem vídeo');
    }

    public function test_video_em_branco_tira_o_video(): void
    {
        $eu = $this->instrutor('Gestor Sem Video');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aulas[0]->id)->update(['url_video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->como($eu['token'])->post($this->pagina($curso, $aulas[0], '/video'), ['video' => ''])->assertRedirect();

        $this->assertNull(Aula::query()->findOrFail($aulas[0]->id)->url_video);
    }

    public function test_link_que_nao_e_video_e_recusado_e_o_video_atual_fica(): void
    {
        $eu = $this->instrutor('Gestor Video Ruim');
        [$curso, $aulas] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aulas[0]->id)->update(['url_video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->como($eu['token'])
            ->post($this->pagina($curso, $aulas[0], '/video'), ['video' => 'https://exemplo.com/nao-e-video'])
            ->assertRedirect($this->pagina($curso, $aulas[0]).'?editar=video#video')
            ->assertSessionHasErrors('video');

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', Aula::query()->findOrFail($aulas[0]->id)->url_video);
    }

    // ---- aula nova --------------------------------------------------------------

    public function test_o_formulario_da_aula_nova_abre_com_a_carga_padrao(): void
    {
        $eu = $this->instrutor('Gestor Nova');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/aulas/nova')
            ->assertOk()
            ->assertDontSee('<div id="root">', false)
            ->assertSee('Nova aula')
            ->assertSee('value="15 min"', false);
    }

    public function test_cria_a_aula_no_fim_da_grade_e_abre_a_pagina_dela(): void
    {
        $eu = $this->instrutor('Gestor Cria');
        [$curso] = $this->cursoComAulas($eu['id']);

        $resposta = $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/aulas', [
            'titulo' => 'Aula criada pela tela',
            'carga' => '20 min',
            'video' => 'https://youtu.be/dQw4w9WgXcQ',
            'texto' => "## Começo\n\nPrimeiro parágrafo.",
        ]);

        $nova = Aula::query()->where('curso_id', $curso->id)->where('titulo', 'Aula criada pela tela')->firstOrFail();
        $resposta->assertRedirect('/inst/curso/'.$curso->slug.'/aulas/'.$nova->id)->assertSessionHas('avisoDoCurso');
        $this->assertSame(4, $nova->ordem);
        $this->assertSame("## Começo\n\nPrimeiro parágrafo.", $nova->conteudo);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $nova->url_video);
        $this->assertSame(4, Aula::query()->where('curso_id', $curso->id)->count());
    }

    public function test_aula_nova_incompleta_volta_ao_formulario_com_o_que_foi_digitado(): void
    {
        $eu = $this->instrutor('Gestor Incompleta');
        [$curso] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas', ['titulo' => 'Sem texto', 'carga' => '10 min', 'texto' => '  '])
            ->assertRedirect('/inst/curso/'.$curso->slug.'/aulas/nova')
            ->assertSessionHasErrors('aula')
            ->assertSessionHasInput('titulo', 'Sem texto');

        $this->assertSame(3, Aula::query()->where('curso_id', $curso->id)->count());
    }

    public function test_a_grade_continua_servida_e_a_aula_criada_aparece_nela(): void
    {
        $eu = $this->instrutor('Gestor Grade Nova');
        [$curso] = $this->cursoComAulas($eu['id']);
        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/aulas', ['titulo' => 'Aula da grade', 'carga' => '5 min', 'texto' => 'Texto']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/grade-curricular')
            ->assertOk()
            ->assertSeeInOrder(['Aula numero 3', 'Aula da grade']);
    }
}
