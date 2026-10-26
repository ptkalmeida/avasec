<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst` em Blade — a escolha de curso, primeira tela da área do instrutor.
 *
 * O que mais importa aqui é a posse: um instrutor não pode ver, nem alcançar,
 * curso que não é dele. A regra vem de `InstructorScope` (FK `instructorId`),
 * a mesma que a API usa — estes testes existem para que a tela nova não abra
 * uma segunda porta ao lado dela.
 */
final class EscolhaDeCursoDoInstrutorTest extends TestCase
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
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return ['id' => $id, 'name' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    private function curso(string $titulo, ?string $instructorId): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-inst-'.uniqid(),
            'title' => $titulo,
            'description' => 'd',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $instructorId,
            'instructorName' => 'Instrutor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst')->assertRedirect('/entrar');
    }

    public function test_aluno_vai_para_a_area_dele(): void
    {
        $aluno = $this->makeStudent('Aluno Tentando Inst');

        $this->como($aluno['token'])->get('/inst')->assertRedirect('/aluno');
    }

    public function test_lista_os_cursos_do_instrutor_servida_pelo_servidor(): void
    {
        $eu = $this->instrutor('Instrutor Com Curso');
        $meu = $this->curso('Curso Que Eu Gerencio', $eu['id']);

        $resposta = $this->como($eu['token'])->get('/inst');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Curso Que Eu Gerencio', escape: false);
        $resposta->assertSee('href="/inst/curso/'.$meu->slug.'"', escape: false);
    }

    /** A regra que não pode afrouxar com a tela nova. */
    public function test_nao_mostra_curso_de_outro_instrutor(): void
    {
        $eu = $this->instrutor('Instrutor Um');
        $outro = $this->instrutor('Instrutor Dois');
        $this->curso('Curso Do Outro Instrutor', $outro['id']);

        $this->como($eu['token'])->get('/inst')
            ->assertDontSee('Curso Do Outro Instrutor', escape: false);
    }

    public function test_instrutor_sem_curso_recebe_instrucao_em_vez_de_lista_vazia(): void
    {
        $eu = $this->instrutor('Instrutor Sem Curso');

        $this->como($eu['token'])->get('/inst')
            ->assertSee('Você ainda não tem curso sob sua responsabilidade.', escape: false);
    }

    /** Admin socorre qualquer curso: a API o trata assim em toda parte. */
    public function test_admin_ve_todos_os_cursos(): void
    {
        $outro = $this->instrutor('Instrutor Do Admin');
        $this->curso('Curso Visto Pelo Admin', $outro['id']);

        $admin = User::query()->where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $this->como(Jwt::issue((string) $admin->id, (string) $admin->name, 'admin'))->get('/inst')
            ->assertOk()
            ->assertSee('Curso Visto Pelo Admin', escape: false);
    }

    /**
     * Curso com a vigência vencida continua na lista, dito por TEXTO. Some da
     * lista seria o instrutor não ter como renovar o que venceu.
     */
    public function test_vigencia_vencida_e_anunciada_sem_esconder_o_curso(): void
    {
        $eu = $this->instrutor('Instrutor Vigencia');
        $curso = $this->curso('Curso Com Vigencia Vencida', $eu['id']);
        Course::query()->whereKey($curso->id)->update(['vigenciaAte' => now()->subDay()->format('Y-m-d')]);

        $this->como($eu['token'])->get('/inst')
            ->assertSee('Curso Com Vigencia Vencida', escape: false)
            ->assertSee('Vigência encerrada', escape: false);
    }

    /** O dia do vencimento vale inteiro: vence HOJE não é vencido. */
    public function test_curso_que_vence_hoje_nao_aparece_como_vencido(): void
    {
        $eu = $this->instrutor('Instrutor Vence Hoje');
        $curso = $this->curso('Curso Que Vence Hoje', $eu['id']);
        Course::query()->whereKey($curso->id)->update(['vigenciaAte' => now()->format('Y-m-d')]);

        $this->como($eu['token'])->get('/inst')
            ->assertSee('Curso Que Vence Hoje', escape: false)
            ->assertDontSee('Vigência encerrada', escape: false);
    }

    /** Curso em rascunho é dele e ele o prepara: tem de aparecer. */
    public function test_curso_nao_publicado_aparece_marcado(): void
    {
        $eu = $this->instrutor('Instrutor Rascunho');
        $curso = $this->curso('Curso Em Rascunho Teste', $eu['id']);
        Course::query()->whereKey($curso->id)->update(['status' => Visibilidade::RASCUNHO]);

        $this->como($eu['token'])->get('/inst')
            ->assertSee('Curso Em Rascunho Teste', escape: false)
            ->assertSee('Não publicado', escape: false);
    }

    /**
     * Curso tirado do ar não volta à tela. É a regra da API, e o painel React
     * também não o mostrava: ressuscitá-lo aqui devolveria à vista do
     * instrutor o que a coordenação removeu.
     */
    public function test_curso_inativado_nao_aparece(): void
    {
        $eu = $this->instrutor('Instrutor Inativado');
        $curso = $this->curso('Curso Tirado Do Ar Teste', $eu['id']);
        $curso->inativar($eu['id'], 'teste');

        $this->como($eu['token'])->get('/inst')
            ->assertDontSee('Curso Tirado Do Ar Teste', escape: false);
    }

    /**
     * Com a aprovação automática ligada — que é a configuração de hoje — não
     * existe matrícula esperando aprovação, e a fila some pela REGRA. O React
     * semeava três solicitações fictícias no navegador.
     */
    public function test_com_aprovacao_automatica_a_fila_nao_anuncia_trabalho(): void
    {
        config(['features.aprovacaoAutomaticaMatricula' => true]);
        $eu = $this->instrutor('Instrutor Fila');
        $this->curso('Curso Da Fila', $eu['id']);

        $this->como($eu['token'])->get('/inst')->assertDontSee('A fazer', escape: false);
    }

    public function test_o_menu_nao_oferece_secao_de_curso_antes_da_escolha(): void
    {
        $eu = $this->instrutor('Instrutor Menu');
        $this->curso('Curso Do Menu', $eu['id']);

        $resposta = $this->como($eu['token'])->get('/inst');

        // Grade, avaliações e alunos são sempre de UM curso.
        $resposta->assertDontSee('Grade curricular', escape: false);
        $resposta->assertSee('Meus cursos', escape: false);
    }

    /**
     * Mensagens fica fora do bloco do curso: a caixa é de todos eles, e antes
     * o item só aparecia depois de escolher um curso.
     */
    public function test_o_menu_oferece_a_caixa_de_mensagens_sem_exigir_curso(): void
    {
        $eu = $this->instrutor('Instrutor Mensagens');
        $this->curso('Curso Com Mensagens', $eu['id']);

        $this->como($eu['token'])->get('/inst')->assertSee('href="/inst/mensagens"', escape: false);
    }

    /** Com as mensagens diretas desligadas, o item some. */
    public function test_o_menu_nao_oferece_mensagens_com_o_canal_fechado(): void
    {
        config(['features.mensagensDiretas' => false]);
        $eu = $this->instrutor('Instrutor Sem Mensagens');
        $this->curso('Curso Sem Mensagens', $eu['id']);

        $this->como($eu['token'])->get('/inst')->assertDontSee('/inst/mensagens', escape: false);
    }
}
