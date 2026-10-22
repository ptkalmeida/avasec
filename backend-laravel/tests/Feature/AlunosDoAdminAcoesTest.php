<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\AdmissionRequest;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Jwt;
use App\Support\SenhaInicial;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GeneratesCpf;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Alunos" do painel administrativo — as escritas (fatia 5 do M5): matricular,
 * redefinir senha, matrícula múltipla e retirar da plataforma.
 *
 * Mexem em conta e em registro acadêmico, então pesa: só o admin escreve; conta
 * e matrícula nascem juntas ou não nascem; nada é apagado; senha respeita a regra
 * de força; e o que o painel antigo inventava (município "São Paulo", UF "SP")
 * não vira dado do aluno.
 */
final class AlunosDoAdminAcoesTest extends TestCase
{
    use DatabaseTransactions;
    use GeneratesCpf;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        // O limitador da redefinição é o mesmo da API e tem teste próprio lá.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function curso(string $titulo, ?string $vigencia = null): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-aacao-'.uniqid(),
            'title' => $titulo,
            'description' => 'Descrição do curso para teste.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorName' => 'Gestor Do Curso',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
            'contractExpirationDate' => $vigencia,
            'vigenciaAte' => $vigencia,
        ]);

        return $curso->refresh();
    }

    /** @return array<string,string> */
    private function formulario(Course $curso, array $sobrescrever = []): array
    {
        return array_merge([
            'name' => 'Clara Ribeiro Nova',
            'email' => 'clara.'.Str::lower(Str::random(8)).'@example.com',
            'cpf' => $this->makeCpf(),
            'curso' => $curso->id,
        ], $sobrescrever);
    }

    // ---- acesso ---------------------------------------------------------------
    public function test_so_o_admin_escreve(): void
    {
        $curso = $this->curso('Curso Acesso Acoes');
        $aluno = $this->makeStudent('Aluno Acao Acesso');
        $gestor = User::query()->where('role', 'instructor')->firstOrFail();
        $tokenDoGestor = Jwt::issue($gestor->id, $gestor->name, 'instructor');

        $this->post('/admin/alunos', $this->formulario($curso))->assertRedirect('/entrar');
        $this->post('/admin/alunos/'.$aluno['id'].'/inativar')->assertRedirect('/entrar');
        $this->como($tokenDoGestor)->post('/admin/alunos', $this->formulario($curso))->assertRedirect('/inst');
        $this->como($aluno['token'])->post('/admin/alunos/'.$aluno['id'].'/senha', ['novaSenha' => 'Outra123456'])->assertRedirect('/aluno');

        $this->assertNull(User::query()->withTrashed()->find($aluno['id'])?->inativadoEm);
    }

    // ---- matricular -----------------------------------------------------------
    public function test_matricula_um_aluno_novo_com_conta_e_matricula_juntas(): void
    {
        $curso = $this->curso('Curso Que Recebe O Aluno');
        $dados = $this->formulario($curso, ['municipio' => 'Niterói', 'uf' => 'rj', 'areaInteresse' => 'Economia Criativa & IA']);

        $resposta = $this->como($this->admin())->post('/admin/alunos', $dados);

        $aluno = User::query()->where('email', $dados['email'])->firstOrFail();
        $resposta->assertRedirect('/admin/alunos/'.$aluno->id);

        $this->assertSame('student', $aluno->role);
        $this->assertSame('active', $aluno->status);
        $this->assertSame('Clara Ribeiro Nova', $aluno->name);
        $this->assertSame(preg_replace('/\D/', '', $dados['cpf']), $aluno->cpf);
        $this->assertSame('Niterói', $aluno->municipio);
        // A sigla digitada em minúscula vira maiúscula.
        $this->assertSame('RJ', $aluno->uf);

        // A matrícula nasce junto, no curso escolhido, aprovada.
        $this->assertSame($curso->id, StudentEnrollment::query()->where('userId', $aluno->id)->value('enrolledCourseId'));
        $this->assertTrue(AdmissionRequest::query()->where('userId', $aluno->id)->where('courseId', $curso->id)->where('status', 'approved')->exists());

        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Cadastro de Usuário')->where('details', 'like', '%Clara Ribeiro Nova%')->exists());
    }

    public function test_sem_senha_o_sistema_gera_uma_que_entra_e_aparece_uma_vez(): void
    {
        $curso = $this->curso('Curso Senha Gerada');
        $dados = $this->formulario($curso);

        $resposta = $this->como($this->admin())->post('/admin/alunos', $dados);
        $aluno = User::query()->where('email', $dados['email'])->firstOrFail();

        $definida = session('senhaDefinida');
        $this->assertIsArray($definida);
        $this->assertSame('Clara Ribeiro Nova', $definida['nome']);
        $this->assertTrue(password_verify($definida['senha'], (string) $aluno->passwordHash));

        $primeira = $this->como($this->admin())->get($resposta->headers->get('Location'));
        $primeira->assertSee($definida['senha']);
        $primeira->assertSee('Ela não será mostrada de novo.');

        // Recarregar não mostra de novo.
        $this->como($this->admin())->get('/admin/alunos/'.$aluno->id)->assertDontSee($definida['senha']);
    }

    public function test_com_senha_informada_ela_vale_e_nao_e_mostrada(): void
    {
        $curso = $this->curso('Curso Senha Informada');
        $dados = $this->formulario($curso, ['senha' => 'Minha123Senha']);

        $this->como($this->admin())->post('/admin/alunos', $dados)->assertSessionMissing('senhaDefinida');

        $aluno = User::query()->where('email', $dados['email'])->firstOrFail();
        $this->assertTrue(password_verify('Minha123Senha', (string) $aluno->passwordHash));
    }

    public function test_municipio_uf_e_area_em_branco_ficam_em_branco(): void
    {
        $curso = $this->curso('Curso Sem Dados Inventados');
        $dados = $this->formulario($curso);

        $this->como($this->admin())->post('/admin/alunos', $dados);

        $aluno = User::query()->where('email', $dados['email'])->firstOrFail();
        $this->assertNull($aluno->municipio);
        $this->assertNull($aluno->uf);
        $this->assertNull($aluno->areaInteresse);
    }

    public function test_cada_campo_aceita_so_o_que_promete(): void
    {
        $curso = $this->curso('Curso Validacao Campos');
        $token = $this->admin();
        $antes = User::query()->count();

        $casos = [
            'name' => [['Clara', 'name'], ['Clara 2 Ribeiro', 'name'], ['Cl@ra Ribeiro', 'name'], ['A B', 'name']],
            'email' => [['clara ribeiro@x.com', 'email'], ['clara@semponto', 'email'], ['123.456.789-09', 'email']],
            'uf' => [['XX', 'uf'], ['12', 'uf'], ['S', 'uf']],
            'municipio' => [['Recife 2', 'municipio']],
            'areaInteresse' => [['Arte <script>', 'areaInteresse']],
            'senha' => [['curta1', 'senha'], ['somenteletras', 'senha'], ['12345678', 'senha']],
            'cpf' => [['111.111.111-11', 'cpf'], ['123', 'cpf']],
        ];

        foreach ($casos as $campo => $lista) {
            foreach ($lista as [$valor, $erroEm]) {
                $this->como($token)->from('/admin/alunos')
                    ->post('/admin/alunos', $this->formulario($curso, [$campo => $valor]))
                    ->assertSessionHasErrors($erroEm);
            }
        }

        $this->assertSame($antes, User::query()->count(), 'nenhuma conta nasce com campo inválido');
    }

    public function test_curso_obrigatorio_inexistente_ou_vencido_e_recusado_sem_criar_conta(): void
    {
        $vencido = $this->curso('Curso Vencido Matricula', '2020-01-01');
        $token = $this->admin();
        $antes = User::query()->count();

        $this->como($token)->from('/admin/alunos')->post('/admin/alunos', $this->formulario($vencido))->assertSessionHasErrors('curso');
        $this->como($token)->from('/admin/alunos')->post('/admin/alunos', $this->formulario($vencido, ['curso' => 'nao-existe']))->assertSessionHasErrors('curso');
        $this->como($token)->from('/admin/alunos')->post('/admin/alunos', $this->formulario($vencido, ['curso' => '']))->assertSessionHasErrors('curso');

        $this->assertSame($antes, User::query()->count());
    }

    public function test_curso_vencido_nao_e_oferecido_no_formulario(): void
    {
        $this->curso('Curso Vencido Fora Do Select', '2020-01-01');
        $this->curso('Curso Em Dia No Select', '2099-12-31');

        $html = (string) $this->como($this->admin())->get('/admin/alunos')->getContent();

        // O curso em dia aparece no filtro E no formulário de matrícula; o vencido
        // só no filtro (listar quem já estava nele é legítimo), nunca na matrícula.
        $this->assertSame(2, substr_count($html, 'Curso Em Dia No Select'));
        $this->assertSame(1, substr_count($html, 'Curso Vencido Fora Do Select'));
    }

    public function test_cpf_ou_email_ja_usados_sao_recusados_e_nao_deixam_conta_pela_metade(): void
    {
        $curso = $this->curso('Curso Duplicado Matricula');
        $existente = $this->makeStudent('Aluno Ja Existente Dup');
        $cpfDele = (string) User::query()->whereKey($existente['id'])->value('cpf');
        $emailDele = (string) User::query()->whereKey($existente['id'])->value('email');
        $antes = User::query()->count();
        $token = $this->admin();

        $this->como($token)->from('/admin/alunos')->post('/admin/alunos', $this->formulario($curso, ['cpf' => $cpfDele]))->assertSessionHasErrors('entrada');
        $this->como($token)->from('/admin/alunos')->post('/admin/alunos', $this->formulario($curso, ['email' => $emailDele]))->assertSessionHasErrors('entrada');

        $this->assertSame($antes, User::query()->count());
    }

    // ---- redefinir senha ------------------------------------------------------
    public function test_redefine_a_senha_do_aluno_e_mostra_uma_vez(): void
    {
        $aluno = $this->makeStudent('Aluno Senha Redefinida');
        $token = $this->admin();

        $resposta = $this->como($token)->post('/admin/alunos/'.$aluno['id'].'/senha', ['novaSenha' => 'Redefinida987']);

        $resposta->assertRedirect('/admin/alunos/'.$aluno['id']);
        $this->assertTrue(password_verify('Redefinida987', (string) User::query()->whereKey($aluno['id'])->value('passwordHash')));
        $this->assertFalse(password_verify('senha123456', (string) User::query()->whereKey($aluno['id'])->value('passwordHash')));

        // Aparece na tela que o redirecionamento abre, e só nela.
        $this->como($token)->get('/admin/alunos/'.$aluno['id'])->assertSee('Redefinida987');
        $this->como($token)->get('/admin/alunos/'.$aluno['id'])->assertDontSee('Redefinida987');
        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Redefinição de Senha')->where('status', 'WARNING')->where('details', 'like', '%'.$aluno['id'].'%')->exists());
    }

    public function test_senha_fraca_nao_e_aceita_e_nao_mexe_na_conta(): void
    {
        $aluno = $this->makeStudent('Aluno Senha Fraca Adm');
        $antes = (string) User::query()->whereKey($aluno['id'])->value('passwordHash');

        foreach (['curta1', 'somenteletras', '12345678'] as $fraca) {
            $this->como($this->admin())->from('/admin/alunos/'.$aluno['id'])
                ->post('/admin/alunos/'.$aluno['id'].'/senha', ['novaSenha' => $fraca])
                ->assertSessionHasErrors('novaSenha');
        }

        $this->assertSame($antes, (string) User::query()->whereKey($aluno['id'])->value('passwordHash'));
    }

    public function test_redefinir_senha_de_quem_nao_e_aluno_responde_404(): void
    {
        $gestor = User::query()->where('role', 'instructor')->firstOrFail();
        $antes = (string) $gestor->passwordHash;

        $this->como($this->admin())->post('/admin/alunos/'.$gestor->id.'/senha', ['novaSenha' => 'Redefinida987'])->assertNotFound();
        $this->assertSame($antes, (string) User::query()->whereKey($gestor->id)->value('passwordHash'));
    }

    public function test_a_senha_sugerida_na_ficha_cumpre_a_regra(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $senha = SenhaInicial::gerar();
            $this->assertGreaterThanOrEqual(8, strlen($senha));
            $this->assertSame(1, preg_match('/\p{L}/u', $senha));
            $this->assertSame(1, preg_match('/\d/', $senha));
            $this->assertSame(0, preg_match('/[0O1lI]/', $senha), 'sem caractere ambíguo');
        }
    }

    // ---- matrícula múltipla ---------------------------------------------------
    public function test_permite_e_revoga_a_matricula_multipla(): void
    {
        $aluno = $this->makeStudent('Aluno Matricula Multipla');
        $token = $this->admin();

        $this->como($token)->post('/admin/alunos/'.$aluno['id'].'/matricula-multipla', ['permitir' => '1'])
            ->assertRedirect('/admin/alunos/'.$aluno['id']);
        $this->assertTrue((bool) StudentEnrollment::query()->where('userId', $aluno['id'])->value('canMultiEnroll'));

        $this->como($token)->post('/admin/alunos/'.$aluno['id'].'/matricula-multipla')->assertRedirect();
        $this->assertFalse((bool) StudentEnrollment::query()->where('userId', $aluno['id'])->value('canMultiEnroll'));
    }

    public function test_com_a_flag_desligada_a_matricula_multipla_nao_existe(): void
    {
        config(['features.matriculasMultiplas' => false]);
        $aluno = $this->makeStudent('Aluno Flag Multipla');

        $this->como($this->admin())->post('/admin/alunos/'.$aluno['id'].'/matricula-multipla', ['permitir' => '1'])->assertNotFound();
        $this->como($this->admin())->get('/admin/alunos/'.$aluno['id'])->assertDontSee('Matrícula múltipla simultânea');
    }

    // ---- retirar da plataforma ------------------------------------------------
    public function test_retirar_inativa_a_conta_e_preserva_tudo(): void
    {
        $curso = $this->curso('Curso Do Aluno Retirado');
        $aluno = $this->makeStudent('Aluno A Ser Retirado');
        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'userId' => $aluno['id'], 'studentName' => $aluno['name'],
            'enrolledCourseId' => $curso->id, 'completedCourseIds' => [], 'extraCourseIds' => [], 'canMultiEnroll' => false,
        ]);

        $this->como($this->admin())->post('/admin/alunos/'.$aluno['id'].'/inativar', ['motivo' => 'Desistência confirmada'])
            ->assertRedirect('/admin/alunos');

        $linha = User::query()->withTrashed()->findOrFail($aluno['id']);
        $this->assertNotNull($linha->inativadoEm);
        $this->assertSame('Desistência confirmada', $linha->motivoInativacao);
        $this->assertSame('blocked', $linha->status);
        $this->assertNotNull($linha->inativadoPor);

        // A matrícula (registro acadêmico) segue intacta.
        $this->assertSame($curso->id, StudentEnrollment::query()->where('userId', $aluno['id'])->value('enrolledCourseId'));

        // Some da lista e da ficha, e não entra mais. (A primeira abertura da lista
        // mostra o aviso do redirecionamento, com o nome; a segunda é a lista.)
        $this->como($this->admin())->get('/admin/alunos')->assertSee('foi retirado(a) da plataforma');
        $this->como($this->admin())->get('/admin/alunos?busca=Aluno+A+Ser+Retirado')->assertSee('Nenhum aluno com esses filtros.');
        $this->como($this->admin())->get('/admin/alunos/'.$aluno['id'])->assertNotFound();
        $this->como($aluno['token'])->get('/aluno')->assertRedirect('/entrar');

        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Inativação de Usuário')->where('details', 'like', '%'.$aluno['id'].'%')->exists());
    }

    public function test_retirar_quem_nao_e_aluno_responde_404_e_nao_mexe(): void
    {
        $gestor = User::query()->where('role', 'instructor')->firstOrFail();

        $this->como($this->admin())->post('/admin/alunos/'.$gestor->id.'/inativar')->assertNotFound();
        $this->assertNull(User::query()->withTrashed()->find($gestor->id)?->inativadoEm);
    }

    public function test_a_ficha_oferece_as_acoes_e_a_lista_o_formulario(): void
    {
        $aluno = $this->makeStudent('Aluno Ve Acoes');
        $token = $this->admin();

        $ficha = $this->como($token)->get('/admin/alunos/'.$aluno['id']);
        $ficha->assertSee('Redefinir senha');
        $ficha->assertSee('Retirar da plataforma');
        $ficha->assertSee('action="/admin/alunos/'.$aluno['id'].'/senha"', false);

        $lista = $this->como($token)->get('/admin/alunos');
        $lista->assertSee('Matricular novo aluno');
        $lista->assertSee('name="cpf"', false);
        $lista->assertSee('name="curso"', false);
    }
}
