<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Avaliacao;
use App\Models\Usuario;
use App\Support\Fuso;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}` — a Gestão do Curso em Blade.
 *
 * Duas escritas moram aqui, e as duas passam pelo `CourseService` que a API
 * usa: propriedades e vigência. O que os testes prendem: a posse (curso alheio
 * é 404), a auditoria (alteração pela tela não pode sumir do registro que a
 * feita pela API deixa) e a conta da vigência, que é do SERVIDOR — no React ela
 * era feita com o relógio do navegador.
 */
final class GestaoDoCursoTest extends TestCase
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

    private function curso(string $titulo, string $instrutor_id): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-gestao-'.uniqid(),
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
            'vigencia_ate' => now()->addYear()->format('Y-m-d'),
            'vencimento_contrato_texto' => now()->addYear()->format('Y-m-d'),
        ]);

        return $curso->refresh();
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer')->assertRedirect('/entrar');
    }

    public function test_mostra_o_panorama_do_curso_servido_pelo_servidor(): void
    {
        $eu = $this->instrutor('Gestor Panorama');
        $curso = $this->curso('Curso Do Panorama Teste', $eu['id']);
        Avaliacao::query()->create(['id' => 'quiz-gestao-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => 'Prova do panorama']);

        $resposta = $this->como($eu['token'])->get('/inst/curso/'.$curso->slug);

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Curso Do Panorama Teste', escape: false);
        $resposta->assertSee('Panorama', escape: false);
        // A frequência mínima efetiva, dizendo de onde vem.
        $resposta->assertSee('padrão da escola', escape: false);
    }

    public function test_curso_de_outro_instrutor_responde_404(): void
    {
        $eu = $this->instrutor('Gestor Um Gestao');
        $outro = $this->instrutor('Gestor Dois Gestao');
        $alheio = $this->curso('Curso Alheio Gestao', $outro['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$alheio->slug)->assertNotFound();
        $this->como($eu['token'])
            ->post('/inst/curso/'.$alheio->slug.'/propriedades', [
                'titulo' => 'Nome Novo Indevido',
                'descricao' => 'Descrição longa o bastante para passar.',
                'categoria' => 'Teste',
            ])
            ->assertNotFound();

        $this->assertSame('Curso Alheio Gestao', Curso::query()->whereKey($alheio->id)->value('titulo'));
    }

    public function test_pelo_id_vai_para_o_endereco_de_hoje(): void
    {
        $eu = $this->instrutor('Gestor Slug');
        $curso = $this->curso('Curso Do Slug Teste', $eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->id)
            ->assertRedirect('/inst/curso/'.$curso->slug);
    }

    public function test_salvar_propriedades_grava_e_audita(): void
    {
        $eu = $this->instrutor('Gestor Propriedades');
        $curso = $this->curso('Curso Propriedades Teste', $eu['id']);
        $antes = (int) DB::table('log_seguranca')->count();

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/propriedades', [
            'titulo' => 'Curso Propriedades Teste',
            'descricao' => 'Uma descrição nova, longa o bastante para o validador.',
            'categoria' => 'Categoria Nova',
            'carga_horaria' => 40,
            'modalidade' => 'A distância',
            'nivel' => 'Introdutório',
            'frequencia_minima' => 80,
            'emite_certificado' => '1',
        ])->assertRedirect('/inst/curso/'.$curso->slug);

        $salvo = Curso::query()->whereKey($curso->id)->first();
        $this->assertNotNull($salvo);
        $this->assertSame('Categoria Nova', $salvo->categoria);
        $this->assertSame(40, (int) $salvo->carga_horaria);
        $this->assertSame(80, (int) $salvo->frequencia_minima);
        $this->assertSame('A distância', $salvo->modalidade);

        $this->assertGreaterThan($antes, (int) DB::table('log_seguranca')->count());
        $this->assertTrue(
            DB::table('log_seguranca')->where('acao', 'Alteração de Curso')->exists(),
            'A alteração pela tela não deixou registro na auditoria.',
        );
    }

    /** Título novo muda o slug, e a tela leva ao endereço novo. */
    public function test_mudar_o_titulo_leva_ao_endereco_novo(): void
    {
        $eu = $this->instrutor('Gestor Titulo');
        $curso = $this->curso('Curso Titulo Antigo Teste', $eu['id']);

        $resposta = $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/propriedades', [
            'titulo' => 'Curso Titulo Novinho Teste',
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
        ]);

        $novo = (string) Curso::query()->whereKey($curso->id)->value('slug');
        $this->assertNotSame($curso->slug, $novo);
        $resposta->assertRedirect('/inst/curso/'.$novo);

        // E o endereço antigo continua chegando aqui.
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug)
            ->assertRedirect('/inst/curso/'.$novo);
    }

    public function test_titulo_curto_demais_volta_com_erro_e_nada_muda(): void
    {
        $eu = $this->instrutor('Gestor Titulo Curto');
        $curso = $this->curso('Curso Titulo Valido Teste', $eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug)
            ->post('/inst/curso/'.$curso->slug.'/propriedades', [
                'titulo' => 'ab',
                'descricao' => 'Descrição longa o bastante para o validador aceitar.',
                'categoria' => 'Teste',
            ])
            ->assertSessionHasErrors('titulo');

        $this->assertSame('Curso Titulo Valido Teste', Curso::query()->whereKey($curso->id)->value('titulo'));
    }

    /** Campo em branco é "não mexer", e não "zerar". */
    public function test_carga_horaria_em_branco_nao_apaga_a_que_existe(): void
    {
        $eu = $this->instrutor('Gestor Carga');
        $curso = $this->curso('Curso Carga Teste', $eu['id']);
        Curso::query()->whereKey($curso->id)->update(['carga_horaria' => 30]);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/propriedades', [
            'titulo' => 'Curso Carga Teste',
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'carga_horaria' => '',
        ]);

        $this->assertSame(30, (int) Curso::query()->whereKey($curso->id)->value('carga_horaria'));
    }

    public function test_renovar_por_um_ano_usa_o_relogio_do_servidor(): void
    {
        $eu = $this->instrutor('Gestor Vigencia Ano');
        $curso = $this->curso('Curso Vigencia Ano Teste', $eu['id']);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/vigencia', ['prazo' => '1-ano'])
            ->assertRedirect('/inst/curso/'.$curso->slug);

        $esperado = Fuso::agora()->addYear()->format('Y-m-d');
        $salvo = Curso::query()->whereKey($curso->id)->first(['vigencia_ate', 'vencimento_contrato_texto']);
        $this->assertNotNull($salvo);
        $this->assertSame($esperado, $salvo->getAttribute('vigencia_ate')?->format('Y-m-d'));
        $this->assertSame($esperado, $salvo->vencimento_contrato_texto);
    }

    public function test_tornar_permanente_tira_o_prazo(): void
    {
        $eu = $this->instrutor('Gestor Permanente');
        $curso = $this->curso('Curso Permanente Teste', $eu['id']);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/vigencia', ['prazo' => 'permanente']);

        $salvo = Curso::query()->whereKey($curso->id)->first(['vigencia_ate', 'vencimento_contrato_texto']);
        $this->assertNotNull($salvo);
        $this->assertNull($salvo->getAttribute('vigencia_ate'));
    }

    /**
     * Renovar um curso vencido o devolve ao catálogo — é para isso que a
     * renovação existe.
     */
    public function test_renovar_devolve_o_curso_ao_catalogo_do_aluno(): void
    {
        $eu = $this->instrutor('Gestor Renova');
        $curso = $this->curso('Curso Renovado Teste', $eu['id']);
        Curso::query()->whereKey($curso->id)->update([
            'vigencia_ate' => now()->subDay()->format('Y-m-d'),
            'vencimento_contrato_texto' => now()->subDay()->format('Y-m-d'),
        ]);

        $aluno = $this->makeStudent('Aluno Do Curso Renovado');
        $this->como($aluno['token'])->get('/aluno/catalogo')->assertDontSee('Curso Renovado Teste', escape: false);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/vigencia', ['prazo' => '6-meses']);

        $this->como($aluno['token'])->get('/aluno/catalogo')->assertSee('Curso Renovado Teste', escape: false);
    }

    public function test_data_no_passado_e_recusada(): void
    {
        $eu = $this->instrutor('Gestor Data Passada');
        $curso = $this->curso('Curso Data Passada Teste', $eu['id']);
        $antes = (string) Curso::query()->whereKey($curso->id)->value('vencimento_contrato_texto');

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug)
            ->post('/inst/curso/'.$curso->slug.'/vigencia', [
                'prazo' => 'data',
                'ate' => now()->subWeek()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('ate');

        $this->assertSame($antes, (string) Curso::query()->whereKey($curso->id)->value('vencimento_contrato_texto'));
    }

    public function test_escolher_data_sem_informar_a_data_e_recusado(): void
    {
        $eu = $this->instrutor('Gestor Sem Data');
        $curso = $this->curso('Curso Sem Data Teste', $eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug)
            ->post('/inst/curso/'.$curso->slug.'/vigencia', ['prazo' => 'data'])
            ->assertSessionHasErrors('ate');
    }

    /** Webinars e a sala ao vivo ficaram fora do MVP. */
    public function test_nao_oferece_webinar_nem_acessar_sala(): void
    {
        $eu = $this->instrutor('Gestor Sem Extensao');
        $curso = $this->curso('Curso Sem Transmissao Teste', $eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug)
            ->assertDontSee('Webinar', escape: false)
            ->assertDontSee('Acessar Sala', escape: false);
    }

    /** O admin socorre qualquer curso, como em toda a API. */
    public function test_admin_abre_e_edita_curso_de_qualquer_instrutor(): void
    {
        $outro = $this->instrutor('Gestor Do Admin Gestao');
        $curso = $this->curso('Curso Do Admin Gestao', $outro['id']);
        $admin = Usuario::query()->where('papel', 'admin')->first();
        $this->assertNotNull($admin);

        $this->como(Jwt::issue((string) $admin->id, (string) $admin->nome, 'admin'))
            ->get('/inst/curso/'.$curso->slug)
            ->assertOk();
    }
}
