<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Usuario;
use App\Services\CourseService;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * O gestor cuida do próprio curso sem depender do admin (ADR 029): ativar ou desativar para o
 * aluno, retirar do ar, reativar e trocar a capa.
 *
 * Pontos que não podem cair: o curso criado pelo gestor NASCE RESTRITO; nada é apagado ao retirar
 * (ADR 12); e a posse vale em todas as ações — gestor de um curso não mexe no de outro.
 */
final class GestorGerenciaOCursoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,nome:string,token:string} */
    private function gestor(string $nome): array
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

    private function curso(string $titulo, string $donoId, int $status = Visibilidade::PUBLICADO): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-gest-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $donoId,
            'nome_instrutor' => 'Gestor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => $status,
        ]);

        return $curso->refresh();
    }

    private function statusDe(Curso $curso): int
    {
        return (int) Curso::withTrashed()->whereKey($curso->id)->value('status');
    }

    private function png(): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent('capa.png', $bytes);
    }

    // ---- nasce restrito ------------------------------------------------------

    public function test_curso_criado_pelo_gestor_nasce_restrito_e_o_aluno_nao_o_alcanca(): void
    {
        $eu = $this->gestor('Gestora Criadora');
        $titulo = 'Nasce Restrito '.Str::random(5);

        $this->como($eu['token'])->post('/inst/cursos', [
            'titulo' => $titulo,
            'categoria' => 'Teste',
            'tipo_curso' => 'fixo',
        ]);

        $curso = Curso::query()->where('titulo', $titulo)->firstOrFail();
        $this->assertSame(Visibilidade::RESTRITO, $this->statusDe($curso));
        $this->assertFalse(Visibilidade::papelAlcanca('student', Visibilidade::RESTRITO));
        $this->assertTrue(Visibilidade::papelAlcanca('instructor', Visibilidade::RESTRITO));
    }

    public function test_o_gestor_continua_vendo_o_proprio_curso_restrito(): void
    {
        $eu = $this->gestor('Gestora Ve');
        $this->curso('Meu Restrito Teste', $eu['id'], Visibilidade::RESTRITO);

        $this->como($eu['token'])->get('/inst')->assertOk()->assertSee('Meu Restrito Teste', escape: false);
    }

    public function test_curso_criado_pelo_admin_ou_pela_api_segue_publicado_por_padrao(): void
    {
        $eu = $this->gestor('Gestora Api');
        $sessao = ['sub' => $eu['id'], 'nome' => $eu['nome'], 'papel' => 'instructor'];

        $criado = app(CourseService::class)->createCourse(['titulo' => 'Padrao Publicado '.Str::random(4), 'categoria' => 'x', 'miniatura' => 'sem-imagem.png', 'descricao' => 'Descrição longa o bastante.'], $sessao);

        $this->assertSame(Visibilidade::PUBLICADO, (int) Curso::query()->whereKey($criado['id'])->value('status'));
    }

    // ---- ativar / desativar para o aluno ---------------------------------------

    public function test_a_tela_do_curso_oferece_o_botao_conforme_o_estado(): void
    {
        $eu = $this->gestor('Gestora Botao');
        $restrito = $this->curso('Curso Botao Restrito', $eu['id'], Visibilidade::RESTRITO);
        $publicado = $this->curso('Curso Botao Publicado', $eu['id'], Visibilidade::PUBLICADO);

        $this->como($eu['token'])->get('/inst/curso/'.$restrito->slug)->assertOk()
            ->assertSee('Ativar para os alunos')
            ->assertSee('value="ativar"', escape: false)
            ->assertSee('Ambiente do aluno: restrito');
        $this->como($eu['token'])->get('/inst/curso/'.$publicado->slug)->assertOk()
            ->assertSee('Desativar para os alunos')
            ->assertSee('value="desativar"', escape: false)
            ->assertSee('Ambiente do aluno: ativo');
    }

    public function test_ativar_publica_e_desativar_restringe(): void
    {
        $eu = $this->gestor('Gestora Alterna');
        $curso = $this->curso('Curso Alterna', $eu['id'], Visibilidade::RESTRITO);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/visibilidade', ['acao' => 'ativar'])
            ->assertRedirect('/inst/curso/'.$curso->slug);
        $this->assertSame(Visibilidade::PUBLICADO, $this->statusDe($curso));

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/visibilidade', ['acao' => 'desativar'])
            ->assertRedirect('/inst/curso/'.$curso->slug);
        $this->assertSame(Visibilidade::RESTRITO, $this->statusDe($curso));
    }

    public function test_acao_invalida_nao_muda_a_visibilidade(): void
    {
        $eu = $this->gestor('Gestora Invalida');
        $curso = $this->curso('Curso Invalido', $eu['id'], Visibilidade::RESTRITO);

        // Rascunho é do admin: nem mandando a ação "rascunho" o gestor chega lá.
        $this->como($eu['token'])->from('/inst/curso/'.$curso->slug)
            ->post('/inst/curso/'.$curso->slug.'/visibilidade', ['acao' => 'rascunho'])
            ->assertSessionHasErrors('acao');

        $this->assertSame(Visibilidade::RESTRITO, $this->statusDe($curso));
    }

    public function test_gestor_de_outro_curso_nao_muda_a_visibilidade(): void
    {
        $dono = $this->gestor('Dono Do Curso');
        $intruso = $this->gestor('Intruso');
        $curso = $this->curso('Curso Do Dono', $dono['id'], Visibilidade::RESTRITO);

        $this->como($intruso['token'])->post('/inst/curso/'.$curso->slug.'/visibilidade', ['acao' => 'ativar'])->assertNotFound();

        $this->assertSame(Visibilidade::RESTRITO, $this->statusDe($curso));
    }

    // ---- retirar e reativar --------------------------------------------------------

    public function test_retirar_inativa_com_quem_quando_e_por_que_e_nao_apaga_nada(): void
    {
        $eu = $this->gestor('Gestora Retira');
        $curso = $this->curso('Curso A Retirar', $eu['id']);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/retirar', ['motivo' => 'Encerrado no semestre'])
            ->assertRedirect('/inst');

        $linha = Curso::withTrashed()->findOrFail($curso->id);
        $this->assertTrue($linha->trashed());
        $this->assertSame($eu['id'], $linha->inativado_por);
        $this->assertSame('Encerrado no semestre', $linha->motivo_inativacao);
        $this->assertNotNull($linha->inativado_em);
        // ADR 12: a linha continua íntegra, com o status de antes.
        $this->assertSame(Visibilidade::PUBLICADO, $this->statusDe($curso));
    }

    public function test_depois_de_retirar_o_curso_aparece_como_inativo_e_a_tela_dele_some(): void
    {
        $eu = $this->gestor('Gestora Ve Inativo');
        $curso = $this->curso('Curso Que Sai', $eu['id']);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/retirar', ['motivo' => 'x']);

        $this->como($eu['token'])->get('/inst')->assertOk()
            ->assertSee('Cursos inativos')
            ->assertSee('Reativar curso');
        // Sem tela própria enquanto retirado: o caminho é reativar pela lista.
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug)->assertNotFound();
    }

    public function test_gestor_de_outro_curso_nao_retira(): void
    {
        $dono = $this->gestor('Dono Retira');
        $intruso = $this->gestor('Intruso Retira');
        $curso = $this->curso('Curso Protegido', $dono['id']);

        $this->como($intruso['token'])->post('/inst/curso/'.$curso->slug.'/retirar', ['motivo' => 'x'])->assertNotFound();

        $this->assertFalse(Curso::withTrashed()->findOrFail($curso->id)->trashed());
    }

    public function test_reativar_devolve_o_curso_como_estava(): void
    {
        $eu = $this->gestor('Gestora Reativa');
        $curso = $this->curso('Curso Reativado', $eu['id'], Visibilidade::RESTRITO);
        $curso->inativar($eu['id'], 'pausa');

        $this->como($eu['token'])->post('/inst/cursos/'.$curso->id.'/reativar')
            ->assertRedirect('/inst')
            ->assertSessionHas('avisoDoCurso');

        $this->assertFalse(Curso::withTrashed()->findOrFail($curso->id)->trashed());
        // Volta COMO ESTAVA: não se publica por baixo dos panos.
        $this->assertSame(Visibilidade::RESTRITO, $this->statusDe($curso));

        $this->como($eu['token'])->get('/inst')->assertSee('Cursos ativos')->assertDontSee('Cursos inativos');
    }

    public function test_gestor_de_outro_curso_nao_reativa(): void
    {
        $dono = $this->gestor('Dono Reativa');
        $intruso = $this->gestor('Intruso Reativa');
        $curso = $this->curso('Curso Retirado Alheio', $dono['id']);
        $curso->inativar($dono['id'], 'x');

        $this->como($intruso['token'])->post('/inst/cursos/'.$curso->id.'/reativar')
            ->assertRedirect('/inst')
            ->assertSessionHasErrors('reativar');

        $this->assertTrue(Curso::withTrashed()->findOrFail($curso->id)->trashed());
    }

    public function test_aluno_e_visitante_nao_reativam(): void
    {
        $dono = $this->gestor('Dono Aluno');
        $curso = $this->curso('Curso Aluno Tenta', $dono['id']);
        $curso->inativar($dono['id'], 'x');

        $this->post('/inst/cursos/'.$curso->id.'/reativar')->assertRedirect('/entrar');
        $aluno = $this->makeStudent('Aluno Tenta Reativar');
        $this->como($aluno['token'])->post('/inst/cursos/'.$curso->id.'/reativar')->assertRedirect('/aluno');

        $this->assertTrue(Curso::withTrashed()->findOrFail($curso->id)->trashed());
    }

    public function test_a_regra_do_servico_vale_tambem_fora_da_tela(): void
    {
        $dono = $this->gestor('Dono Servico');
        $outro = $this->gestor('Outro Servico');
        $curso = $this->curso('Curso Servico', $dono['id']);
        $curso->inativar($dono['id'], 'x');
        $servico = app(CourseService::class);

        try {
            $servico->reactivateCourse($curso->id, ['sub' => $outro['id'], 'nome' => 'o', 'papel' => 'instructor']);
            $this->fail('quem não gerencia o curso não pode reativá-lo');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->status);
        }

        $servico->reactivateCourse($curso->id, ['sub' => $dono['id'], 'nome' => 'd', 'papel' => 'instructor']);
        $this->assertFalse(Curso::withTrashed()->findOrFail($curso->id)->trashed());
    }

    // ---- capa ------------------------------------------------------------------------

    public function test_gestor_troca_e_remove_a_capa_do_proprio_curso(): void
    {
        Storage::fake('publico');
        $eu = $this->gestor('Gestora Capa');
        $curso = $this->curso('Curso Com Capa', $eu['id']);

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/capa', ['capa' => $this->png()])
            ->assertRedirect('/inst/curso/'.$curso->slug);

        $capa = (string) Curso::query()->whereKey($curso->id)->value('imagem_capa');
        $this->assertStringStartsWith('/uploads/', $capa);
        Storage::disk('publico')->assertExists(basename($capa));

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug)->assertSee($capa, escape: false)->assertSee('Remover capa');

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/capa', ['remover' => '1'])->assertRedirect();
        $this->assertNull(Curso::query()->whereKey($curso->id)->value('imagem_capa'));
    }

    public function test_capa_que_nao_e_imagem_e_recusada(): void
    {
        Storage::fake('publico');
        $eu = $this->gestor('Gestora Capa Ruim');
        $curso = $this->curso('Curso Capa Ruim', $eu['id']);

        $this->como($eu['token'])->from('/inst/curso/'.$curso->slug)
            ->post('/inst/curso/'.$curso->slug.'/capa', ['capa' => UploadedFile::fake()->createWithContent('virus.exe', 'MZ binario')])
            ->assertSessionHasErrors('capa');

        $this->assertNull(Curso::query()->whereKey($curso->id)->value('imagem_capa'));
    }

    public function test_gestor_de_outro_curso_nao_troca_a_capa(): void
    {
        Storage::fake('publico');
        $dono = $this->gestor('Dono Capa');
        $intruso = $this->gestor('Intruso Capa');
        $curso = $this->curso('Curso Capa Alheio', $dono['id']);

        $this->como($intruso['token'])->post('/inst/curso/'.$curso->slug.'/capa', ['capa' => $this->png()])->assertNotFound();

        $this->assertNull(Curso::query()->whereKey($curso->id)->value('imagem_capa'));
    }

    public function test_as_acoes_novas_exigem_login_de_gestor(): void
    {
        $dono = $this->gestor('Dono Login');
        $curso = $this->curso('Curso Login', $dono['id']);

        foreach (['visibilidade', 'capa', 'retirar'] as $acao) {
            $this->post('/inst/curso/'.$curso->slug.'/'.$acao)->assertRedirect('/entrar');
        }
        $this->assertSame(Visibilidade::PUBLICADO, $this->statusDe($curso));
    }
}
