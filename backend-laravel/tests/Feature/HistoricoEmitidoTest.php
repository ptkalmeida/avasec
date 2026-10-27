<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\HistoricoEmitido;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Histórico escolar: o admin emite, o aluno vê e baixa na área dele (decisão de
 * 27/10/2026). Cada emissão é uma linha nova; ninguém além do dono (e do admin) baixa.
 */
final class HistoricoEmitidoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array<string, mixed> */
    private function alunoComCertificado(string $nome): array
    {
        $aluno = $this->makeStudent($nome);
        $cursoId = 'course-hist-'.uniqid();
        $aulaId = 'lesson-hist-'.uniqid();
        DB::table('curso')->insert([
            'id' => $cursoId, 'titulo' => 'Curso do Histórico', 'descricao' => 'd', 'categoria' => 'x',
            'miniatura' => 't', 'nome_instrutor' => 'Gestor', 'carga_horaria' => 40,
        ]);
        DB::table('aula')->insert(['id' => $aulaId, 'curso_id' => $cursoId, 'titulo' => 'A1', 'duracao' => '5min', 'ordem' => 0]);
        $this->matricular($aluno, $cursoId);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-hist-'.uniqid(), 'nome_aluno' => $aluno['nome'], 'usuario_id' => $aluno['id'], 'curso_id' => $cursoId,
            'aulas_concluidas' => json_encode([$aulaId]), 'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);
        $this->withHeader('Authorization', 'Bearer '.$aluno['token'])
            ->postJson('/api/certificados', ['curso_id' => $cursoId])->assertStatus(201);
        $this->flushHeaders();

        return $aluno;
    }

    public function test_admin_emite_e_o_aluno_ve_e_baixa_na_area_dele(): void
    {
        $aluno = $this->alunoComCertificado('Aluna Historico Um');

        $this->como($this->staffToken('admin'))->post('/admin/alunos/'.$aluno['id'].'/historico')
            ->assertRedirect('/admin/alunos/'.$aluno['id'])->assertSessionHas('avisoDoAluno');

        $emitido = HistoricoEmitido::query()->where('usuario_id', $aluno['id'])->firstOrFail();
        $this->assertSame('Curso do Histórico', $emitido->dados['linhas'][0]['curso']);
        $this->assertSame('40 horas', $emitido->dados['linhas'][0]['carga_horaria']);

        $this->como($aluno['token'])->get('/aluno/certificados')
            ->assertOk()->assertSee('Histórico escolar')->assertSee('/aluno/historico/'.$emitido->id.'/pdf', false);

        $pdf = $this->como($aluno['token'])->get('/aluno/historico/'.$emitido->id.'/pdf');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
    }

    public function test_sem_emissao_a_secao_nao_aparece_para_o_aluno(): void
    {
        $aluno = $this->makeStudent('Aluna Sem Historico');

        $this->como($aluno['token'])->get('/aluno/certificados')->assertOk()->assertDontSee('Baixar PDF');
    }

    public function test_aluno_sem_curso_concluido_nao_gera_historico_vazio(): void
    {
        $aluno = $this->makeStudent('Aluna Sem Conclusao');

        $this->como($this->staffToken('admin'))->post('/admin/alunos/'.$aluno['id'].'/historico')
            ->assertSessionHasErrors('historico');
        $this->assertSame(0, HistoricoEmitido::query()->where('usuario_id', $aluno['id'])->count());
    }

    public function test_emitir_de_novo_acrescenta_uma_linha_e_nao_substitui(): void
    {
        $aluno = $this->alunoComCertificado('Aluna Historico Dois');
        $admin = $this->staffToken('admin');

        $this->como($admin)->post('/admin/alunos/'.$aluno['id'].'/historico');
        $this->como($admin)->post('/admin/alunos/'.$aluno['id'].'/historico');

        $this->assertSame(2, HistoricoEmitido::query()->where('usuario_id', $aluno['id'])->count());
    }

    public function test_so_o_admin_emite(): void
    {
        $aluno = $this->alunoComCertificado('Aluna Historico Tres');

        $this->como($aluno['token'])->post('/admin/alunos/'.$aluno['id'].'/historico')->assertRedirect('/aluno');
        $this->assertSame(0, HistoricoEmitido::query()->where('usuario_id', $aluno['id'])->count());
    }

    public function test_aluno_nao_baixa_historico_de_outro(): void
    {
        $dona = $this->alunoComCertificado('Aluna Dona');
        $outro = $this->makeStudent('Aluno Curioso');
        $this->como($this->staffToken('admin'))->post('/admin/alunos/'.$dona['id'].'/historico');
        $id = HistoricoEmitido::query()->where('usuario_id', $dona['id'])->firstOrFail()->id;

        $this->como($outro['token'])->get('/aluno/historico/'.$id.'/pdf')->assertForbidden();
    }

    public function test_historico_inativado_some_da_area_do_aluno(): void
    {
        $aluno = $this->alunoComCertificado('Aluna Historico Quatro');
        $this->como($this->staffToken('admin'))->post('/admin/alunos/'.$aluno['id'].'/historico');
        $emitido = HistoricoEmitido::query()->where('usuario_id', $aluno['id'])->firstOrFail();

        $emitido->inativar($aluno['id'], 'emitido por engano');

        $this->como($aluno['token'])->get('/aluno/historico/'.$emitido->id.'/pdf')->assertNotFound();
        $this->assertNotNull(HistoricoEmitido::withTrashed()->find($emitido->id), 'a linha permanece (ADR 12)');
    }
}
