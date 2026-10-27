<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Fuso;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Cursos & Trilhas" do painel administrativo (fatia 4 do M5).
 *
 * Mexe em curso — registro acadêmico —, então o que mais pesa: só o admin
 * escreve, nada é apagado ao "retirar do ar" (ADR 12), a autoria só muda para
 * um gestor de verdade, e a lista de alunos é a de matrículas reais.
 */
final class CursosDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function gestor(string $nome): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);
    }

    private function curso(string $titulo, ?string $gestorId = null, int $status = Visibilidade::PUBLICADO): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-cadm-'.uniqid(),
            'title' => $titulo,
            'description' => 'Descrição do curso para teste.',
            'category' => 'Teste Admin',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $gestorId,
            'instructorName' => 'Gestor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => $status,
        ]);

        return $curso->refresh();
    }

    private function png(): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent('capa.png', $bytes);
    }

    // ---- acesso ---------------------------------------------------------------
    public function test_so_o_admin_chega_e_so_ele_escreve(): void
    {
        $curso = $this->curso('Curso Acesso Admin');

        $this->get('/admin/cursos')->assertRedirect('/entrar');
        $this->post('/admin/cursos', ['title' => 'x'])->assertRedirect('/entrar');

        $gestor = $this->gestor('Gestor Sem Acesso Admin');
        $token = Jwt::issue($gestor->id, $gestor->name, 'instructor');
        $this->como($token)->get('/admin/cursos')->assertRedirect('/inst');
        $this->como($token)->post('/admin/cursos/'.$curso->id.'/retirar')->assertRedirect('/inst');

        $this->assertNull(Course::query()->find($curso->id)?->inativadoEm);
    }

    public function test_a_flag_do_catalogo_desligada_responde_404(): void
    {
        config(['features.catalogoCursos' => false]);
        $curso = $this->curso('Curso Flag Desligada');

        $this->como($this->admin())->get('/admin/cursos')->assertNotFound();
        $this->como($this->admin())->post('/admin/cursos/'.$curso->id.'/retirar')->assertNotFound();
        $this->assertNull(Course::query()->find($curso->id)?->inativadoEm);
    }

    // ---- ler ------------------------------------------------------------------
    public function test_lista_o_curso_com_aulas_responsavel_e_estado(): void
    {
        $gestor = $this->gestor('Gestora Lista Cursos');
        $this->curso('Curso Que Aparece Na Lista', $gestor->id);

        $resposta = $this->como($this->admin())->get('/admin/cursos?busca=Que+Aparece+Na+Lista');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Curso Que Aparece Na Lista');
        $resposta->assertSee('Ativo no AVA');
        $resposta->assertSee('Publicado');
        $resposta->assertSee('Retirar do ar');
    }

    public function test_a_busca_acha_pelo_titulo_ou_pelo_professor(): void
    {
        $gestor = $this->gestor('Professor Raríssimo Zxy');
        $curso = Course::query()->create([
            'id' => 'course-cadm-busca', 'title' => 'Curso Busca Titulo Qwv', 'description' => 'Descrição do curso.',
            'category' => 'T', 'thumbnail' => 'x.png', 'instructorId' => $gestor->id, 'instructorName' => $gestor->name,
        ]);
        Course::query()->whereKey($curso->id)->update(['slug' => 'curso-busca-qwv', 'status' => 1]);
        $token = $this->admin();

        $this->como($token)->get('/admin/cursos?busca=Titulo+Qwv')->assertSee('Curso Busca Titulo Qwv');
        $this->como($token)->get('/admin/cursos?busca=Rar%C3%ADssimo+Zxy')->assertSee('Curso Busca Titulo Qwv');
        $this->como($token)->get('/admin/cursos?busca=%25')->assertDontSee('Curso Busca Titulo Qwv');
    }

    /**
     * No painel React cada curso mostrava de 2 a 4 alunos escolhidos por uma
     * conta sobre as letras do id do curso. Aqui só aparece quem está
     * matriculado — no curso principal ou num concorrente — e quem concluiu não
     * está "ativo".
     */
    public function test_alunos_ativos_sao_os_matriculados_de_verdade(): void
    {
        $curso = $this->curso('Curso Alunos Reais Admin');
        $outro = $this->curso('Outro Curso Alunos Admin');

        $principal = $this->makeStudent('Aluna Principal Real');
        $extra = $this->makeStudent('Aluna Concorrente Real');
        $concluida = $this->makeStudent('Aluna Concluiu Real');
        $naoMatriculada = $this->makeStudent('Aluna Sem Matricula Real');

        foreach ([
            [$principal, $curso->id, [], []],
            [$extra, $outro->id, [$curso->id], []],
            [$concluida, null, [], [$curso->id]],
        ] as [$aluno, $principalId, $extras, $concluidos]) {
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'userId' => $aluno['id'], 'studentName' => $aluno['name'],
                'enrolledCourseId' => $principalId, 'completedCourseIds' => $concluidos,
                'extraCourseIds' => $extras, 'canMultiEnroll' => $extras !== [],
            ]);
        }

        $resposta = $this->como($this->admin())->get('/admin/cursos?busca=Curso+Alunos+Reais+Admin');

        $resposta->assertSee('Alunos ativos (2)');
        $resposta->assertSee('Aluna Principal Real');
        $resposta->assertSee('Aluna Concorrente Real');
        $resposta->assertDontSee('Aluna Concluiu Real');
        $resposta->assertDontSee($naoMatriculada['name']);
    }

    public function test_curso_sem_aluno_diz_que_nao_ha_matriculados(): void
    {
        $this->curso('Curso Vazio Sem Alunos');

        $this->como($this->admin())->get('/admin/cursos?busca=Curso+Vazio+Sem+Alunos')
            ->assertSee('Alunos ativos (0)')
            ->assertSee('Nenhum aluno matriculado neste curso.');
    }

    public function test_curso_vencido_aparece_como_expirado_e_o_inativado_some(): void
    {
        $vencido = $this->curso('Curso Vencido Admin Kjh');
        Course::query()->whereKey($vencido->id)->update(['contractExpirationDate' => '2020-01-01', 'vigenciaAte' => '2020-01-01']);
        $inativo = $this->curso('Curso Inativo Admin Kjh');
        $inativo->inativar('x', 'teste');

        $resposta = $this->como($this->admin())->get('/admin/cursos?busca=Admin+Kjh');

        $resposta->assertSee('Curso Vencido Admin Kjh');
        $resposta->assertSee('Expirado (arquivado)');
        $resposta->assertSee('Reativar curso');

        // O retirado não é um curso ativo: fora do catálogo, mas — desde 26/10/2026 — presente
        // na seção "Cursos retirados do ar", em vez de sumir da tela (o "limbo").
        // `RetiradosDoAdminTest` cobre a reativação.
        $html = $resposta->getContent();
        $this->assertIsString($html);
        $secao = strpos($html, 'Cursos retirados do ar (');
        $this->assertNotFalse($secao, 'A seção de retirados precisa existir.');
        $this->assertStringNotContainsString('Curso Inativo Admin Kjh', substr($html, 0, $secao), 'O retirado não pode estar entre os ativos.');
        $this->assertStringContainsString('Curso Inativo Admin Kjh', substr($html, $secao), 'O retirado precisa estar na seção de retirados.');
    }

    // ---- prover ---------------------------------------------------------------
    public function test_prover_um_curso_com_area_nova_responsavel_vigencia_e_chat(): void
    {
        $gestor = $this->gestor('Gestor Que Recebe O Curso');

        $resposta = $this->como($this->admin())->post('/admin/cursos', [
            'title' => 'Curso Provido Pelo Admin',
            'category' => 'Área Inventada Agora',
            'instructorId' => $gestor->id,
            'description' => 'Uma ementa com mais de dez letras.',
            'vigencia' => '2031-05-20',
            'courseType' => 'ao_vivo',
            'hasChat' => '1',
        ]);

        $curso = Course::query()->where('title', 'Curso Provido Pelo Admin')->firstOrFail();
        $resposta->assertRedirect('/admin/cursos?curso='.$curso->id.'#curso-'.$curso->id);

        $this->assertSame('Área Inventada Agora', $curso->category);
        $this->assertSame($gestor->id, $curso->instructorId);
        $this->assertSame($gestor->name, $curso->instructorName);
        $this->assertSame('ao_vivo', $curso->courseType);
        $this->assertTrue((bool) $curso->hasChat);
        $this->assertSame('2031-05-20', substr((string) $curso->contractExpirationDate, 0, 10));
        $this->assertSame('2031-05-20', substr((string) $curso->vigenciaAte, 0, 10));
        $this->assertNotSame('', (string) $curso->slug);
        $this->assertStringStartsWith('https://images.unsplash.com/', (string) $curso->thumbnail);

        // A área nova passa a ser oferecida.
        $this->como($this->admin())->get('/admin/cursos')->assertSee('Área Inventada Agora');

        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Criação de Curso')->where('details', 'like', '%Curso Provido Pelo Admin%')->exists());
    }

    public function test_curso_sem_ementa_ganha_a_descricao_padrao_e_sem_chat_marcado_fica_sem_chat(): void
    {
        $this->como($this->admin())->post('/admin/cursos', [
            'title' => 'Curso Sem Ementa Admin',
            'category' => 'Qualquer',
            'courseType' => 'fixo',
        ])->assertRedirect();

        $curso = Course::query()->where('title', 'Curso Sem Ementa Admin')->firstOrFail();
        $this->assertStringContainsString('recém-provido', (string) $curso->description);
        $this->assertFalse((bool) $curso->hasChat);
        $this->assertNull($curso->contractExpirationDate);
    }

    public function test_titulo_curto_e_responsavel_inexistente_sao_recusados(): void
    {
        $token = $this->admin();
        $antes = Course::query()->count();

        $this->como($token)->from('/admin/cursos')->post('/admin/cursos', [
            'title' => 'ab', 'category' => 'X', 'courseType' => 'fixo',
        ])->assertSessionHasErrors('title');

        $this->como($token)->from('/admin/cursos')->post('/admin/cursos', [
            'title' => 'Curso Responsavel Falso', 'category' => 'X', 'courseType' => 'fixo',
            'instructorId' => 'nao-existe-esse-id',
        ])->assertSessionHasErrors('instructorId');

        $this->assertSame($antes, Course::query()->count());
    }

    // ---- cuidar ---------------------------------------------------------------
    public function test_trocar_o_professor_so_aceita_gestor_de_verdade(): void
    {
        $de = $this->gestor('Gestor De Origem');
        $para = $this->gestor('Gestor De Destino');
        $curso = $this->curso('Curso Troca Professor', $de->id);
        $aluno = $this->makeStudent('Aluno Que Nao E Gestor');
        $token = $this->admin();

        $this->como($token)->post('/admin/cursos/'.$curso->id.'/instrutor', ['instructorId' => $para->id])
            ->assertRedirect('/admin/cursos?curso='.$curso->id.'#curso-'.$curso->id);
        $curso->refresh();
        $this->assertSame($para->id, $curso->instructorId);
        $this->assertSame($para->name, $curso->instructorName);

        // Aluno não vira responsável.
        $this->como($token)->from('/admin/cursos')->post('/admin/cursos/'.$curso->id.'/instrutor', ['instructorId' => $aluno['id']])
            ->assertSessionHasErrors('instructorId');
        $this->assertSame($para->id, $curso->refresh()->instructorId);
    }

    public function test_salvar_tipo_e_chat(): void
    {
        $curso = $this->curso('Curso Tipo E Chat');
        Course::query()->whereKey($curso->id)->update(['hasChat' => true, 'courseType' => 'fixo']);
        $token = $this->admin();

        // Chat desmarcado = ausente do formulário.
        $this->como($token)->post('/admin/cursos/'.$curso->id.'/propriedades', ['courseType' => 'ao_vivo'])->assertRedirect();
        $curso->refresh();
        $this->assertSame('ao_vivo', $curso->courseType);
        $this->assertFalse((bool) $curso->hasChat);

        $this->como($token)->post('/admin/cursos/'.$curso->id.'/propriedades', ['courseType' => 'fixo', 'hasChat' => '1'])->assertRedirect();
        $curso->refresh();
        $this->assertSame('fixo', $curso->courseType);
        $this->assertTrue((bool) $curso->hasChat);

        $this->como($token)->from('/admin/cursos')->post('/admin/cursos/'.$curso->id.'/propriedades', ['courseType' => 'inventado'])
            ->assertSessionHasErrors('courseType');
    }

    public function test_vigencia_por_data_um_ano_e_permanente(): void
    {
        $curso = $this->curso('Curso Vigencia Admin');
        $token = $this->admin();
        $base = '/admin/cursos/'.$curso->id.'/vigencia';

        // Uma data já passada vale: é como o admin encerra um curso de propósito.
        $this->como($token)->post($base, ['prazo' => 'data', 'ate' => '2020-02-02'])->assertRedirect();
        $this->assertSame('2020-02-02', substr((string) $curso->refresh()->vigenciaAte, 0, 10));

        $this->como($token)->post($base, ['prazo' => '1-ano'])->assertRedirect();
        $this->assertSame(Fuso::agora()->addYear()->format('Y-m-d'), substr((string) $curso->refresh()->vigenciaAte, 0, 10));

        $this->como($token)->post($base, ['prazo' => 'permanente'])->assertRedirect();
        $curso->refresh();
        $this->assertNull($curso->vigenciaAte);
        $this->assertNull($curso->contractExpirationDate);

        $this->como($token)->from('/admin/cursos')->post($base, ['prazo' => 'data'])->assertSessionHasErrors('ate');
        $this->como($token)->from('/admin/cursos')->post($base, ['prazo' => 'data', 'ate' => '31/12/2030'])->assertSessionHasErrors('ate');
    }

    public function test_reativar_um_curso_vencido_o_devolve_ao_ar(): void
    {
        $curso = $this->curso('Curso Para Reativar');
        Course::query()->whereKey($curso->id)->update(['contractExpirationDate' => '2020-01-01', 'vigenciaAte' => '2020-01-01']);

        $this->como($this->admin())->post('/admin/cursos/'.$curso->id.'/vigencia', ['prazo' => '1-ano'])->assertRedirect();

        $this->como($this->admin())->get('/admin/cursos?busca=Curso+Para+Reativar')
            ->assertSee('Ativo no AVA')
            ->assertDontSee('Expirado (arquivado)');
    }

    public function test_capa_enviada_vira_a_capa_e_pode_ser_removida(): void
    {
        Storage::fake('publico');
        $curso = $this->curso('Curso Com Capa');
        $token = $this->admin();

        $this->como($token)->post('/admin/cursos/'.$curso->id.'/capa', ['capa' => $this->png()])->assertRedirect();

        $curso->refresh();
        $this->assertStringStartsWith('/uploads/', (string) $curso->coverImage);
        Storage::disk('publico')->assertExists(basename((string) $curso->coverImage));

        $this->como($token)->post('/admin/cursos/'.$curso->id.'/capa', ['remover' => '1'])->assertRedirect();
        $this->assertNull($curso->refresh()->coverImage);
    }

    public function test_capa_de_tipo_proibido_ou_ausente_e_recusada(): void
    {
        Storage::fake('publico');
        $curso = $this->curso('Curso Capa Recusada');
        $token = $this->admin();

        $this->como($token)->from('/admin/cursos')->post('/admin/cursos/'.$curso->id.'/capa', [
            'capa' => UploadedFile::fake()->createWithContent('virus.exe', 'MZ binario'),
        ])->assertSessionHasErrors('capa');

        $this->como($token)->from('/admin/cursos')->post('/admin/cursos/'.$curso->id.'/capa', [])->assertSessionHasErrors('capa');

        $this->assertNull($curso->refresh()->coverImage);
    }

    // ---- retirar do ar --------------------------------------------------------
    public function test_retirar_do_ar_inativa_e_nao_apaga(): void
    {
        $curso = $this->curso('Curso Para Retirar');
        $aluno = $this->makeStudent('Aluno Do Curso Retirado');
        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(), 'userId' => $aluno['id'], 'studentName' => $aluno['name'],
            'enrolledCourseId' => $curso->id, 'completedCourseIds' => [], 'extraCourseIds' => [], 'canMultiEnroll' => false,
        ]);

        $this->como($this->admin())->post('/admin/cursos/'.$curso->id.'/retirar', ['motivo' => 'Contrato encerrado'])
            ->assertRedirect('/admin/cursos');

        // Fora das consultas padrão, mas a linha está lá, com quem, quando e por quê.
        $this->assertNull(Course::query()->find($curso->id));
        $linha = Course::query()->withTrashed()->findOrFail($curso->id);
        $this->assertNotNull($linha->inativadoEm);
        $this->assertSame('Contrato encerrado', $linha->motivoInativacao);
        $this->assertNotNull($linha->inativadoPor);

        // E a matrícula continua intacta.
        $this->assertSame($curso->id, StudentEnrollment::query()->where('userId', $aluno['id'])->value('enrolledCourseId'));

        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Inativação de Curso')->where('details', 'like', '%'.$curso->id.'%')->exists());
    }

    public function test_curso_inexistente_responde_404(): void
    {
        $this->como($this->admin())->post('/admin/cursos/nao-existe/retirar')->assertNotFound();
        $this->como($this->admin())->post('/admin/cursos/nao-existe/vigencia', ['prazo' => 'permanente'])->assertNotFound();
    }
}
