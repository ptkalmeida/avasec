<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\CursoGestor;
use App\Models\Usuario;
use App\Services\GestoresDoCurso;
use App\Support\InstructorScope;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Equipe do painel administrativo: criar gestor (já com os cursos), trocar os cursos
 * depois, retirar e reativar — e o curso com mais de um gestor, ou nenhum
 * (tabela `curso_gestor`, decisão do responsável em 26-27/10/2026).
 */
final class GestoresDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** Cada requisição de um teste reaproveita o cookie da anterior; limpa-o ao trocar de pessoa. */
    private function semCookies(): void
    {
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    private function gestor(string $nome): Usuario
    {
        return Usuario::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
            'status' => 'active',
        ]);
    }

    private function curso(string $titulo, ?Usuario $gestor = null): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-gx-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'd',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $gestor?->id,
            'nome_instrutor' => $gestor !== null ? $gestor->nome : '',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso;
    }

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function tokenDe(Usuario $u): string
    {
        return Jwt::issue($u->id, $u->nome, 'instructor');
    }

    public function test_admin_cria_gestor_com_cursos_e_a_senha_aparece_uma_vez(): void
    {
        $semGestor = $this->curso('Curso Sem Dono Gx');
        $comDono = $this->curso('Curso Com Dono Gx', $this->gestor('Dona Original Gx'));

        $resposta = $this->como($this->admin())->post('/admin/equipe', [
            'nome' => 'Marina Souza Gx',
            'email' => 'marina.gx'.Str::lower(Str::random(6)).'@example.com',
            'cursos' => [$semGestor->id, $comDono->id],
        ]);

        $resposta->assertRedirect('/admin/equipe');
        $resposta->assertSessionHas('senhaDefinida');

        $nova = Usuario::query()->where('nome', 'Marina Souza Gx')->firstOrFail();
        $this->assertSame('instructor', $nova->papel);
        $this->assertSame('active', $nova->status, 'Criada pela coordenação, a conta nasce ativa.');

        // Sem responsável, ela vira a responsável; com responsável, entra como gestora adicional.
        $this->assertSame($nova->id, Curso::query()->findOrFail($semGestor->id)->instrutor_id);
        $this->assertSame('Marina Souza Gx', Curso::query()->findOrFail($semGestor->id)->nome_instrutor);
        $this->assertNotSame($nova->id, Curso::query()->findOrFail($comDono->id)->instrutor_id);
        $this->assertTrue(CursoGestor::query()->where('curso_id', $comDono->id)->where('usuario_id', $nova->id)->exists());

        $this->assertEqualsCanonicalizing([$semGestor->id, $comDono->id], InstructorScope::courseIds(['sub' => $nova->id, 'nome' => $nova->nome, 'papel' => 'instructor']));
    }

    public function test_gestor_adicional_abre_o_curso_e_outro_gestor_nao(): void
    {
        $dono = $this->gestor('Dono Gx');
        $adicional = $this->gestor('Adicional Gx');
        $alheio = $this->gestor('Alheio Gx');
        $curso = $this->curso('Curso Compartilhado Gx', $dono);
        CursoGestor::query()->create(['curso_id' => $curso->id, 'usuario_id' => $adicional->id]);

        $slug = (string) Curso::query()->findOrFail($curso->id)->slug;

        $this->como($this->tokenDe($dono))->get('/inst/curso/'.$slug)->assertOk();
        $this->semCookies();
        $this->como($this->tokenDe($adicional))->get('/inst/curso/'.$slug)->assertOk();
        $this->semCookies();
        $this->como($this->tokenDe($alheio))->get('/inst/curso/'.$slug)->assertNotFound();
    }

    public function test_curso_pode_ficar_sem_gestor_e_a_tela_avisa(): void
    {
        $this->curso('Curso Orfao Gx');

        $this->como($this->admin())->get('/admin/equipe')
            ->assertOk()
            ->assertSee('Cursos sem gestor ativo')
            ->assertSee('Curso Orfao Gx');
    }

    public function test_editar_cursos_vincula_e_desvincula_e_promove_o_adicional(): void
    {
        $dono = $this->gestor('Dono Troca Gx');
        $adicional = $this->gestor('Adicional Troca Gx');
        $curso = $this->curso('Curso Troca Gx', $dono);
        CursoGestor::query()->create(['curso_id' => $curso->id, 'usuario_id' => $adicional->id]);

        // O dono sai: o adicional passa a ser o responsável, e a linha dele não fica duplicada.
        $this->como($this->admin())->post('/admin/equipe/'.$dono->id.'/cursos', ['cursos' => []])
            ->assertRedirect('/admin/equipe');
        $curso->refresh();
        $this->assertSame($adicional->id, $curso->instrutor_id);
        $this->assertSame('Adicional Troca Gx', $curso->nome_instrutor);
        $this->assertFalse((new GestoresDoCurso)->gerencia($curso->id, $dono->id));
        $this->assertTrue((new GestoresDoCurso)->gerencia($curso->id, $adicional->id));

        // O último sai: o curso fica sem gestor, e continua existindo.
        $this->como($this->admin())->post('/admin/equipe/'.$adicional->id.'/cursos', ['cursos' => []]);
        $curso->refresh();
        $this->assertNull($curso->instrutor_id);
        $this->assertSame('', $curso->nome_instrutor);
    }

    public function test_vincular_de_novo_reativa_a_mesma_linha_em_vez_de_duplicar(): void
    {
        $dono = $this->gestor('Dono Reativa Gx');
        $outro = $this->gestor('Outro Reativa Gx');
        $curso = $this->curso('Curso Reativa Gx', $dono);

        $servico = new GestoresDoCurso;
        $servico->definirCursosDoGestor($outro->id, [$curso->id]);
        $servico->definirCursosDoGestor($outro->id, []);
        $this->assertFalse($servico->gerencia($curso->id, $outro->id));
        // ADR 12: a linha foi inativada, não apagada.
        $this->assertSame(1, CursoGestor::withTrashed()->where('curso_id', $curso->id)->where('usuario_id', $outro->id)->count());

        $servico->definirCursosDoGestor($outro->id, [$curso->id]);
        $this->assertTrue($servico->gerencia($curso->id, $outro->id));
        $this->assertSame(1, CursoGestor::withTrashed()->where('curso_id', $curso->id)->where('usuario_id', $outro->id)->count());
    }

    public function test_retirar_nao_pede_motivo_mantem_os_vinculos_e_reativar_devolve(): void
    {
        $gestor = $this->gestor('Retirada Gx');
        $curso = $this->curso('Curso Da Retirada Gx', $gestor);

        $this->como($this->admin())->post('/admin/equipe/'.$gestor->id.'/retirar')->assertRedirect('/admin/equipe');

        $retirada = Usuario::withTrashed()->findOrFail($gestor->id);
        $this->assertTrue($retirada->estaInativo());
        $this->assertSame('blocked', $retirada->status);
        $this->assertSame($gestor->id, Curso::query()->findOrFail($curso->id)->instrutor_id, 'O vínculo não é desfeito.');

        $html = (string) $this->como($this->admin())->get('/admin/equipe')->getContent();
        $this->assertStringContainsString('Cursos sem gestor ativo', $html);
        $this->assertStringContainsString('Curso Da Retirada Gx', $html);

        $this->como($this->admin())->post('/admin/equipe/'.$gestor->id.'/reativar')->assertRedirect('/admin/equipe');

        $this->assertSame('active', Usuario::query()->findOrFail($gestor->id)->status);
        $this->assertTrue((new GestoresDoCurso)->gerencia($curso->id, $gestor->id));
        $depois = (string) $this->como($this->admin())->get('/admin/equipe')->getContent();
        $this->assertStringNotContainsString('Cursos sem gestor ativo ('.'1)', $depois);
    }

    public function test_cadastro_recusa_nome_ruim_e_email_repetido(): void
    {
        $existente = $this->gestor('Ja Existe Gx');

        $this->como($this->admin())->post('/admin/equipe', ['nome' => 'Sozinho', 'email' => 'a@b.com'])
            ->assertSessionHasErrors('nome', null, 'novoGestor');

        $this->como($this->admin())->post('/admin/equipe', ['nome' => 'Nome Completo Gx', 'email' => (string) $existente->email])
            ->assertSessionHasErrors('email', null, 'novoGestor');

        $this->assertSame(0, Usuario::query()->where('nome', 'Nome Completo Gx')->count());
    }

    public function test_so_o_admin_cria_edita_e_retira(): void
    {
        $gestor = $this->gestor('Intocavel Gx');
        $aluno = $this->makeStudent('Aluno Gx');

        $this->post('/admin/equipe', ['nome' => 'Fulano de Tal', 'email' => 'f@x.com'])->assertRedirect('/entrar');

        $this->como($aluno['token'])->post('/admin/equipe/'.$gestor->id.'/retirar')->assertRedirect('/aluno');
        $this->semCookies();
        $this->como($this->tokenDe($gestor))->post('/admin/equipe/'.$gestor->id.'/cursos', ['cursos' => []])->assertRedirect('/inst');

        $this->assertFalse(Usuario::query()->findOrFail($gestor->id)->estaInativo());
    }

    public function test_aluno_nao_e_tratado_como_gestor(): void
    {
        $aluno = $this->makeStudent('Aluno Nao Gestor Gx');

        $this->como($this->admin())->post('/admin/equipe/'.$aluno['id'].'/retirar')->assertNotFound();
        $this->como($this->admin())->post('/admin/equipe/'.$aluno['id'].'/cursos', ['cursos' => []])->assertNotFound();
    }
}
