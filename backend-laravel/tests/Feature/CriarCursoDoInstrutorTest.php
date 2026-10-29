<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Usuario;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * O gestor cria curso (disciplina) novo pela área dele.
 *
 * O botão "Cadastrar Novo Curso" do painel React ficou de fora quando `/inst` virou Blade e
 * nunca voltou: o gestor não tinha como criar disciplina. Estes testes prendem a volta — e,
 * o que mais importa, que o curso nasce SEMPRE em nome de quem criou.
 */
final class CriarCursoDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,nome:string,token:string} */
    private function instrutor(string $nome = 'Gestora Nova'): array
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

    /** @return array<string, string> */
    private function dadosValidos(string $titulo): array
    {
        return [
            'titulo' => $titulo,
            'categoria' => 'Produção Cultural',
            'descricao' => 'Ementa do curso de teste, com mais de dez caracteres.',
            'tipo_curso' => 'fixo',
            'tem_chat' => '1',
        ];
    }

    public function test_a_tela_meus_cursos_oferece_o_botao(): void
    {
        $eu = $this->instrutor();

        $this->como($eu['token'])->get('/inst')
            ->assertOk()
            ->assertSee('href="/inst/cursos/novo"', escape: false)
            ->assertSee('Cadastrar novo curso');
    }

    public function test_o_formulario_abre_com_os_campos_da_criacao(): void
    {
        $eu = $this->instrutor();

        $this->como($eu['token'])->get('/inst/cursos/novo')
            ->assertOk()
            ->assertSee('action="/inst/cursos"', escape: false)
            ->assertSee('name="titulo"', escape: false)
            ->assertSee('name="categoria"', escape: false)
            ->assertSee('name="descricao"', escape: false)
            ->assertSee('name="tipo_curso"', escape: false)
            ->assertSee('name="vigencia"', escape: false)
            ->assertSee('name="tem_chat"', escape: false)
            // Não há campo de professor: o curso é sempre de quem cria.
            ->assertDontSee('name="instrutor_id"', escape: false);
    }

    public function test_cria_o_curso_em_nome_do_gestor_e_leva_para_a_tela_dele(): void
    {
        $eu = $this->instrutor('Gestora Criadora');
        $titulo = 'Curso Criado Pelo Gestor '.Str::random(5);

        $resposta = $this->como($eu['token'])->post('/inst/cursos', $this->dadosValidos($titulo) + ['vigencia' => '2027-12-31']);

        $curso = Curso::query()->where('titulo', $titulo)->first();
        $this->assertNotNull($curso, 'o curso não foi criado');
        $this->assertSame($eu['id'], $curso->instrutor_id);
        $this->assertSame('Gestora Criadora', $curso->nome_instrutor);
        $this->assertSame('Produção Cultural', $curso->categoria);
        $this->assertSame('fixo', $curso->tipo_curso);
        $this->assertTrue((bool) $curso->tem_chat);
        $this->assertNotSame('', (string) $curso->miniatura);

        $resposta->assertRedirect('/inst/curso/'.$curso->slug);

        // A tela do curso abre e diz o que aconteceu.
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug)
            ->assertOk()
            ->assertSee('criado', escape: false);
    }

    public function test_o_curso_novo_aparece_em_meus_cursos(): void
    {
        $eu = $this->instrutor();
        $titulo = 'Disciplina Visivel '.Str::random(5);

        $this->como($eu['token'])->post('/inst/cursos', $this->dadosValidos($titulo));

        $this->como($eu['token'])->get('/inst')->assertOk()->assertSee($titulo);
    }

    public function test_sem_ementa_entra_o_texto_padrao(): void
    {
        $eu = $this->instrutor();
        $titulo = 'Sem Ementa '.Str::random(5);
        $dados = $this->dadosValidos($titulo);
        unset($dados['descricao']);

        $this->como($eu['token'])->post('/inst/cursos', $dados);

        $this->assertSame(
            'Ementa em elaboração pelo professor responsável.',
            Curso::query()->where('titulo', $titulo)->value('descricao'),
        );
    }

    /** O ponto que não pode cair: nem mandando o `instrutor_id` de outra pessoa. */
    public function test_nao_da_para_criar_curso_em_nome_de_outro_gestor(): void
    {
        $eu = $this->instrutor('Eu Mesma');
        $outro = $this->instrutor('Outra Pessoa');
        $titulo = 'Tentativa De Autoria '.Str::random(5);

        $this->como($eu['token'])->post('/inst/cursos', $this->dadosValidos($titulo) + ['instrutor_id' => $outro['id']]);

        $curso = Curso::query()->where('titulo', $titulo)->first();
        $this->assertNotNull($curso);
        $this->assertSame($eu['id'], $curso->instrutor_id);
    }

    public function test_dados_invalidos_voltam_ao_formulario_sem_criar_nada(): void
    {
        $eu = $this->instrutor();
        $antes = Curso::query()->count();

        $this->como($eu['token'])->from('/inst/cursos/novo')
            ->post('/inst/cursos', ['titulo' => 'ab', 'categoria' => '', 'tipo_curso' => 'invalido', 'vigencia' => '31/12/2027'])
            ->assertRedirect('/inst/cursos/novo')
            ->assertSessionHasErrors(['titulo', 'categoria', 'tipo_curso', 'vigencia']);

        // As mensagens saem em português, não nas do idioma padrão do framework.
        $erros = session('errors');
        $this->assertSame('O título precisa ter pelo menos 3 caracteres.', $erros?->first('titulo'));
        $this->assertSame('Informe a área ou categoria do curso.', $erros?->first('categoria'));
        $this->assertSame('Escolha um tipo de curso válido.', $erros?->first('tipo_curso'));

        $this->assertSame($antes, Curso::query()->count());
    }

    public function test_o_titulo_digitado_volta_no_formulario_depois_do_erro(): void
    {
        $eu = $this->instrutor();

        $this->como($eu['token'])->from('/inst/cursos/novo')
            ->post('/inst/cursos', ['titulo' => 'Titulo Ok', 'categoria' => '', 'tipo_curso' => 'fixo']);

        $this->como($eu['token'])->get('/inst/cursos/novo')->assertSee('value="Titulo Ok"', escape: false);
    }

    public function test_visitante_e_aluno_nao_chegam_ao_formulario_nem_criam(): void
    {
        $this->get('/inst/cursos/novo')->assertRedirect('/entrar');
        $this->post('/inst/cursos', $this->dadosValidos('Visitante Tenta'))->assertRedirect('/entrar');

        $aluno = $this->makeStudent('Aluno Tentando Criar');
        $this->como($aluno['token'])->get('/inst/cursos/novo')->assertRedirect('/aluno');
        $this->como($aluno['token'])->post('/inst/cursos', $this->dadosValidos('Aluno Tenta'))->assertRedirect('/aluno');

        $this->assertNull(Curso::query()->where('titulo', 'Aluno Tenta')->first());
        $this->assertNull(Curso::query()->where('titulo', 'Visitante Tenta')->first());
    }

    public function test_com_o_catalogo_desligado_a_criacao_responde_404(): void
    {
        config(['features.catalogoCursos' => false]);
        $eu = $this->instrutor();

        $this->como($eu['token'])->get('/inst/cursos/novo')->assertNotFound();
        $this->como($eu['token'])->post('/inst/cursos', $this->dadosValidos('Catalogo Off'))->assertNotFound();
    }
}
