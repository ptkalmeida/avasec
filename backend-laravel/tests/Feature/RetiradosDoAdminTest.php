<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Usuario;
use App\Services\AuthService;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Retirados" do painel administrativo — o fim do limbo (26/10/2026).
 *
 * Nada é apagado (ADR 12), mas até esta seção nenhuma tela mostrava o que saía do ar:
 * ficava guardado no banco, sem como voltar pela interface. Aqui se prova as duas
 * metades: o que foi retirado **aparece**, separado dos ativos e com quem/quando/por
 * quê; e **reativar devolve tudo** — curso para os gestores e para os alunos, conta
 * com acesso de novo.
 */
final class RetiradosDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function adminId(): string
    {
        return (string) Usuario::query()->where('papel', 'admin')->where('status', 'active')->value('id');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** Curso publicado, sem vigência, com 2 aulas — o que o aluno veria no catálogo. */
    private function curso(string $titulo, int $visibilidade = Visibilidade::PUBLICADO, ?string $vigencia = null): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-ret-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição do curso para teste.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'nome_instrutor' => 'Gestor Do Curso',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => $visibilidade,
            'vencimento_contrato_texto' => $vigencia,
            'vigencia_ate' => $vigencia,
        ]);
        foreach (['a1', 'a2'] as $n) {
            Aula::query()->create(['id' => $curso->id.'-'.$n, 'curso_id' => $curso->id, 'titulo' => $n, 'conteudo' => 'x', 'duracao' => '10', 'ordem' => 1]);
        }

        return $curso->refresh();
    }

    private function retirar(Curso $curso, ?string $motivo = null): void
    {
        Curso::query()->findOrFail($curso->id)->inativar($this->adminId(), $motivo);
    }

    /** @return array{id:string,name:string,email:string,token:string} */
    private function gestor(string $nome): array
    {
        $email = 'gestor-'.uniqid().'@example.com';
        $res = $this->withHeader('Authorization', 'Bearer '.$this->admin())->postJson('/api/autenticacao/cadastro', [
            'nome' => $nome.' '.uniqid(),
            'email' => $email,
            'password' => 'senha123456',
            'papel' => 'instructor',
        ]);
        $res->assertStatus(201);
        $this->flushHeaders();
        $id = (string) $res->json('usuario.id');
        $name = (string) $res->json('usuario.nome');

        return ['id' => $id, 'nome' => $name, 'email' => $email, 'token' => Jwt::issue($id, $name, 'instructor')];
    }

    /** Posição de um texto no HTML — para provar que está numa seção, e não na outra. */
    private function posicao(string $html, string $texto): int
    {
        $pos = strpos($html, $texto);
        $this->assertNotFalse($pos, "Não achei \"{$texto}\" na tela.");

        return (int) $pos;
    }

    /**
     * ÚLTIMA posição de um texto. A mensagem de confirmação ("Curso X retirado do ar") fica
     * na sessão e reaparece no topo da página seguinte, citando o nome; a primeira ocorrência
     * seria o aviso, e não a lista. O item da seção "Retirados" é sempre o último.
     */
    private function ultimaPosicao(string $html, string $texto): int
    {
        $pos = strrpos($html, $texto);
        $this->assertNotFalse($pos, "Não achei \"{$texto}\" na tela.");

        return (int) $pos;
    }

    /** Conta retirada não entra: 401 (conta não encontrada) ou 403 — o que importa é não entrar. */
    private function assertNaoEntra(array $credenciais): void
    {
        $this->assertContains($this->postJson('/api/autenticacao/entrar', $credenciais)->getStatusCode(), [401, 403]);
    }

    /** O cookie de sessão do teste persiste entre requisições; zera para simular quem não entrou. */
    private function semCookies(): self
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        return $this;
    }

    // ---- cursos: o limbo acabou ------------------------------------------------

    public function test_curso_retirado_aparece_na_secao_propria_e_sai_dos_ativos(): void
    {
        $retirado = $this->curso('Curso Retirado Do Limbo Zq');
        $this->curso('Curso Que Segue Ativo Zq');

        $this->como($this->admin())
            ->post('/admin/cursos/'.$retirado->id.'/retirar', ['motivo' => 'Revisão do conteúdo em outubro'])
            ->assertRedirect('/admin/cursos');

        $html = $this->como($this->admin())->get('/admin/cursos')->assertOk()->getContent();
        $this->assertIsString($html);

        $secao = $this->posicao($html, 'Cursos retirados do ar (');
        // O retirado está DEPOIS do título da seção de retirados; o ativo, antes dele.
        $this->assertGreaterThan($secao, $this->ultimaPosicao($html, 'Curso Retirado Do Limbo Zq'));
        $this->assertLessThan($secao, $this->posicao($html, 'Curso Que Segue Ativo Zq'));
        // Quem, quando (formato local) e por quê — o que o ADR 12 promete guardar.
        $this->assertStringContainsString('Revisão do conteúdo em outubro', $html);
        $this->assertStringContainsString('Reativar', $html);
        $this->assertStringContainsString('2 aulas', $html);
    }

    public function test_sem_motivo_a_tela_diz_nao_informado_em_vez_de_esconder(): void
    {
        $curso = $this->curso('Curso Sem Motivo Dito Zq');
        $this->retirar($curso);

        $this->como($this->admin())->get('/admin/cursos')
            ->assertOk()
            ->assertSee('Curso Sem Motivo Dito Zq')
            ->assertSee('não informado');
    }

    /**
     * O que o limbo tirava: reativar devolve tudo, e o ALUNO volta a poder se matricular.
     * O par "retirado → 404, reativado → 200" é a prova; uma coluna mudando de valor não é.
     */
    public function test_reativar_devolve_o_curso_inteiro_e_os_alunos_voltam_a_se_matricular(): void
    {
        $curso = $this->curso('Curso Que Volta Ao Ar Zq');
        $aluno = $this->makeStudent('Aluno Do Curso Que Volta');
        $this->retirar($curso, 'Pausa');

        // Retirado: some do catálogo e o aluno não consegue se matricular.
        $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso->id])
            ->assertNotFound();
        $this->flushHeaders();

        $this->como($this->admin())
            ->post('/admin/cursos/'.$curso->id.'/reativar')
            ->assertRedirect('/admin/cursos')
            ->assertSessionHas('avisoDosCursos', fn (string $m): bool => str_contains($m, 'voltou ao ar') && str_contains($m, 'Já está aberto a matrículas'));

        $voltou = Curso::query()->find($curso->id);
        $this->assertNotNull($voltou);
        $this->assertNull($voltou->inativado_em);
        $this->assertNull($voltou->inativado_por);
        $this->assertNull($voltou->motivo_inativacao);
        // As aulas nunca saíram: voltam junto, sem recompor nada.
        $this->assertSame(2, Aula::query()->where('curso_id', $curso->id)->count());

        // Reativado: o aluno se matricula.
        $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso->id])
            ->assertOk();
        $this->flushHeaders();

        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Reativação de Curso')->where('detalhes', 'like', '%'.$curso->id.'%')->exists());
    }

    /** O curso volta COMO ESTAVA: a tela não finge um "no ar" que o aluno não veria. */
    public function test_reativar_avisa_quando_o_curso_volta_fechado_aos_alunos(): void
    {
        $rascunho = $this->curso('Curso Rascunho Reativado Zq', Visibilidade::RASCUNHO);
        $vencido = $this->curso('Curso Vencido Reativado Zq', Visibilidade::PUBLICADO, '2020-01-31');
        $this->retirar($rascunho);
        $this->retirar($vencido);

        $this->como($this->admin())->post('/admin/cursos/'.$rascunho->id.'/reativar')
            ->assertSessionHas('avisoDosCursos', fn (string $m): bool => str_contains($m, 'Rascunho') && str_contains($m, 'só o veem depois de publicado'));

        $this->como($this->admin())->post('/admin/cursos/'.$vencido->id.'/reativar')
            ->assertSessionHas('avisoDosCursos', fn (string $m): bool => str_contains($m, 'vigência venceu') && str_contains($m, 'não entram matrículas novas'));

        // Voltou como estava: não foi publicado nem teve a vigência estendida por baixo dos panos.
        $this->assertSame(Visibilidade::RASCUNHO, (int) Curso::query()->find($rascunho->id)?->getAttribute('status'));
        $this->assertSame('2020-01-31', Curso::query()->find($vencido->id)?->getAttribute('vigencia_ate')?->format('Y-m-d') ?? (string) Curso::query()->find($vencido->id)?->getAttribute('vigencia_ate'));
    }

    public function test_reativar_avisa_quando_o_gestor_do_curso_esta_retirado(): void
    {
        $gestor = $this->gestor('Gestor Que Saiu');
        $curso = $this->curso('Curso Do Gestor Retirado Zq');
        Curso::query()->whereKey($curso->id)->update(['instrutor_id' => $gestor['id'], 'nome_instrutor' => $gestor['nome']]);
        $this->retirar($curso);
        app(AuthService::class)->deleteUser($gestor['id'], ['sub' => $this->adminId(), 'nome' => 'Admin', 'papel' => 'admin']);

        $this->como($this->admin())->post('/admin/cursos/'.$curso->id.'/reativar')
            ->assertSessionHas('avisoDosCursos', fn (string $m): bool => str_contains($m, 'está retirado') && str_contains($m, $gestor['nome']));
    }

    public function test_so_o_admin_reativa_e_so_o_que_esta_retirado(): void
    {
        $retirado = $this->curso('Curso Que Ninguem Reativa Zq');
        $ativo = $this->curso('Curso Que Ja Esta Ativo Zq');
        $this->retirar($retirado);
        $gestor = $this->gestor('Gestor Sem Poder');

        $this->post('/admin/cursos/'.$retirado->id.'/reativar')->assertRedirect('/entrar');
        $this->como($gestor['token'])->post('/admin/cursos/'.$retirado->id.'/reativar')->assertRedirect('/inst');
        $this->assertTrue(Curso::query()->withTrashed()->find($retirado->id)?->trashed());

        // Duplo clique / curso que nunca saiu: não há o que reativar, e nada é alterado.
        $this->como($this->admin())->post('/admin/cursos/'.$ativo->id.'/reativar')->assertNotFound();
        $this->assertFalse(Curso::query()->withTrashed()->find($ativo->id)?->trashed());
    }

    // ---- alunos ----------------------------------------------------------------

    public function test_aluno_retirado_aparece_separado_e_reativar_devolve_o_acesso(): void
    {
        $aluno = $this->makeStudent('Aluno Retirado Do Limbo');
        $curso = $this->curso('Curso Do Aluno Retirado Zq');
        $this->matricular($aluno, $curso->id);

        $this->como($this->admin())
            ->post('/admin/alunos/'.$aluno['id'].'/inativar', ['motivo' => 'Pediu saída da escola'])
            ->assertRedirect('/admin/alunos');

        $html = $this->como($this->admin())->get('/admin/alunos')->assertOk()->getContent();
        $this->assertIsString($html);
        $secao = $this->posicao($html, 'Alunos retirados da plataforma (');
        $this->assertGreaterThan($secao, $this->ultimaPosicao($html, $aluno['nome']));
        $this->assertStringContainsString('Pediu saída da escola', $html);

        // Retirado, ele não entra.
        $credenciais = ['cpf' => $aluno['cpf'], 'password' => 'senha123456'];
        $this->assertNaoEntra($credenciais);

        $this->como($this->admin())
            ->post('/admin/alunos/'.$aluno['id'].'/reativar')
            ->assertRedirect('/admin/alunos')
            ->assertSessionHas('avisoDosAlunos', fn (string $m): bool => str_contains($m, 'voltou à plataforma'));

        $voltou = Usuario::query()->find($aluno['id']);
        $this->assertNotNull($voltou);
        $this->assertSame('active', $voltou->status);
        $this->assertNull($voltou->inativado_em);
        $this->assertNull($voltou->motivo_inativacao);

        // Reativado, entra de novo — e a matrícula de antes continua lá.
        $this->postJson('/api/autenticacao/entrar', $credenciais)->assertOk();
        $this->assertSame($curso->id, DB::table('matricula')->where('usuario_id', $aluno['id'])->value('curso_matriculado_id'));

        $this->assertTrue(DB::table('log_seguranca')->where('acao', 'Reativação de Usuário')->where('detalhes', 'like', '%'.$aluno['id'].'%')->exists());
    }

    /** Um token de ANTES da retirada não pode voltar a valer só porque a conta voltou (ADR 22). */
    public function test_reativar_encerra_as_sessoes_antigas(): void
    {
        $aluno = $this->makeStudent('Aluno Sessao Antiga');
        $antes = (int) Usuario::query()->whereKey($aluno['id'])->value('versao_da_sessao');

        $this->como($this->admin())->post('/admin/alunos/'.$aluno['id'].'/inativar');
        $this->como($this->admin())->post('/admin/alunos/'.$aluno['id'].'/reativar');

        $depois = (int) Usuario::query()->whereKey($aluno['id'])->value('versao_da_sessao');
        $this->assertGreaterThan($antes, $depois, 'A versão da sessão deve avançar: o token antigo deixa de valer.');
    }

    public function test_nao_se_reativa_gestor_pela_rota_de_aluno_nem_aluno_pela_de_equipe(): void
    {
        $aluno = $this->makeStudent('Aluno Rota Cruzada');
        $gestor = $this->gestor('Gestor Rota Cruzada');
        $admin = ['sub' => $this->adminId(), 'nome' => 'Admin', 'papel' => 'admin'];
        app(AuthService::class)->deleteUser($aluno['id'], $admin);
        app(AuthService::class)->deleteUser($gestor['id'], $admin);

        $this->como($this->admin())->post('/admin/alunos/'.$gestor['id'].'/reativar')->assertNotFound();
        $this->como($this->admin())->post('/admin/equipe/'.$aluno['id'].'/reativar')->assertNotFound();

        $this->assertTrue(Usuario::query()->withTrashed()->find($aluno['id'])?->trashed());
        $this->assertTrue(Usuario::query()->withTrashed()->find($gestor['id'])?->trashed());
    }

    // ---- equipe ----------------------------------------------------------------

    public function test_gestor_retirado_aparece_separado_e_reativar_devolve_o_acesso(): void
    {
        $gestor = $this->gestor('Gestor Retirado Do Limbo');
        $admin = ['sub' => $this->adminId(), 'nome' => 'Admin', 'papel' => 'admin'];
        app(AuthService::class)->deleteUser($gestor['id'], $admin, 'Encerrou o contrato');

        $html = $this->como($this->admin())->get('/admin/equipe')->assertOk()->getContent();
        $this->assertIsString($html);
        $secao = $this->posicao($html, 'Gestores retirados da equipe (');
        $this->assertGreaterThan($secao, $this->ultimaPosicao($html, $gestor['nome']));
        $this->assertStringContainsString('Encerrou o contrato', $html);
        // Retirado, ele NÃO aparece na lista dos ativos (que vem antes da seção).
        $this->assertStringNotContainsString($gestor['nome'], substr($html, 0, $secao));

        $credenciais = ['email' => $gestor['email'], 'password' => 'senha123456'];
        $this->assertNaoEntra($credenciais);

        $this->como($this->admin())
            ->post('/admin/equipe/'.$gestor['id'].'/reativar')
            ->assertRedirect('/admin/equipe')
            ->assertSessionHas('avisoDaEquipe', fn (string $m): bool => str_contains($m, 'voltou à equipe'));

        $this->assertSame('active', Usuario::query()->find($gestor['id'])?->status);
        $this->postJson('/api/autenticacao/entrar', $credenciais)->assertOk();
    }

    public function test_so_o_admin_chega_aos_retirados(): void
    {
        $aluno = $this->makeStudent('Aluno Sem Acesso Retirados');
        $gestor = $this->gestor('Gestor Sem Acesso Retirados');

        foreach (['/admin/cursos', '/admin/alunos', '/admin/equipe'] as $rota) {
            $this->semCookies()->get($rota)->assertRedirect('/entrar');
            $this->semCookies()->como($gestor['token'])->get($rota)->assertRedirect('/inst');
            $this->semCookies()->como($aluno['token'])->get($rota)->assertRedirect('/aluno');
        }
    }
}
