<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Ciclo completo dos exercícios práticos: lançar, entregar e corrigir.
 *
 * Por que existe: a flag `atividadesPraticasAvancadas` viveu desligada, então
 * TODA rota daqui devolvia 404 e o cliente — que engolia erro com `.catch()` —
 * seguia mostrando exercício, entrega e nota que só existiam no localStorage.
 * Com a flag ligada, o contrato passa a ser exercitado de verdade; estes testes
 * fixam qual método vai em qual rota, que foi exatamente o que o cliente errava.
 */
final class PracticalExerciseTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['features.atividadesPraticasAvancadas' => true]);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** Curso do instrutor do seed, para as asserções de posse valerem. */
    private function cursoDoInstrutor(): string
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $courseId = DB::table('Course')->where('instructorId', $instructorId)->value('id');
        $this->assertNotNull($courseId, 'Seed sem curso do instrutor — popular o MySQL de dev.');

        return $courseId;
    }

    public function test_flag_desligada_devolve_404_em_toda_a_area(): void
    {
        // Flag é chave de liga/desliga: com ela off a API some inteira, e é isso
        // que fazia a tela parecer "funcionando" contra o localStorage.
        config(['features.atividadesPraticasAvancadas' => false]);
        $token = $this->staffToken('admin');

        $this->getJson('/api/exercises', $this->auth($token))->assertStatus(404);
        $this->getJson('/api/exercise-submissions', $this->auth($token))->assertStatus(404);
    }

    public function test_instrutor_lanca_edita_e_remove_exercicio_do_proprio_curso(): void
    {
        $token = $this->staffToken('instructor');
        $courseId = $this->cursoDoInstrutor();
        $id = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercises', [
            'id' => $id,
            'courseId' => $courseId,
            'title' => 'Análise de heurísticas',
            'description' => 'Objetivo da atividade.',
            'instructions' => 'Entregue um relatório de 500 palavras.',
            'maxPoints' => 80,
            'dueDate' => '30/09/2026',
        ], $this->auth($token))->assertStatus(201)->assertJsonPath('maxPoints', 80);

        // PUT /exercises/{id} é a rota de edição. O cliente mandava POST na rota
        // de criação e a edição simplesmente não acontecia.
        $this->putJson("/api/exercises/{$id}", [
            'title' => 'Análise de heurísticas (revisada)',
            'maxPoints' => 90,
        ], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('title', 'Análise de heurísticas (revisada)')
            ->assertJsonPath('maxPoints', 90);

        $this->deleteJson("/api/exercises/{$id}", [], $this->auth($token))->assertOk();
        $this->assertDatabaseMissing('PracticalExercise', ['id' => $id]);
    }

    public function test_prazo_em_texto_livre_e_recusado(): void
    {
        $token = $this->staffToken('instructor');

        $this->postJson('/api/exercises', [
            'courseId' => $this->cursoDoInstrutor(),
            'title' => 'Com prazo inválido',
            'description' => 'd',
            'instructions' => 'i',
            'maxPoints' => 100,
            'dueDate' => 'julho de 2026',
        ], $this->auth($token))->assertStatus(400);
    }

    public function test_exercicio_pode_nascer_sem_prazo(): void
    {
        $token = $this->staffToken('instructor');

        $this->postJson('/api/exercises', [
            'courseId' => $this->cursoDoInstrutor(),
            'title' => 'Sem prazo',
            'description' => 'd',
            'instructions' => 'i',
            'maxPoints' => 100,
        ], $this->auth($token))->assertStatus(201)->assertJsonPath('dueDate', null);
    }

    public function test_aluno_nao_lanca_exercicio(): void
    {
        $aluno = $this->makeStudent('Aluno Exercicio');

        $this->postJson('/api/exercises', [
            'courseId' => $this->cursoDoInstrutor(),
            'title' => 'Tentativa',
            'description' => 'd',
            'instructions' => 'i',
            'maxPoints' => 100,
        ], $this->auth($aluno['token']))->assertStatus(403);
    }

    public function test_entrega_e_correcao_pelas_rotas_certas(): void
    {
        $instrutor = $this->staffToken('instructor');
        $courseId = $this->cursoDoInstrutor();
        $exId = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercises', [
            'id' => $exId,
            'courseId' => $courseId,
            'title' => 'Entrega e correção',
            'description' => 'd',
            'instructions' => 'i',
            'maxPoints' => 100,
        ], $this->auth($instrutor))->assertStatus(201);

        $aluno = $this->makeStudent('Aluno Entrega');
        DB::table('AdmissionRequest')->insert([
            'id' => 'adm-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $courseId,
            'status' => 'approved',
            'submittedAt' => '01/09/2026',
        ]);

        $entrega = $this->postJson('/api/exercise-submissions', [
            'exerciseId' => $exId,
            'submissionText' => 'Minha análise das heurísticas.',
        ], $this->auth($aluno['token']))->assertStatus(201);

        $subId = $entrega->json('id');
        // `submittedAt` e `status` são do servidor: data de entrega é registro
        // acadêmico, não o relógio do navegador do aluno.
        $entrega->assertJsonPath('status', 'pending');
        $this->assertNotEmpty($entrega->json('submittedAt'));

        // Reenviar atualiza a MESMA entrega, não cria uma segunda.
        $this->postJson('/api/exercise-submissions', [
            'exerciseId' => $exId,
            'submissionText' => 'Versão revisada.',
        ], $this->auth($aluno['token']))->assertStatus(201)->assertJsonPath('id', $subId);
        $this->assertSame(1, DB::table('ExerciseSubmission')->where('exerciseId', $exId)->count());

        // A rota de correção é PUT /{id}/grade. O cliente lançava nota com POST em
        // /exercise-submissions — que é `role:student` — e recebia 403 em silêncio.
        $this->postJson('/api/exercise-submissions', [
            'exerciseId' => $exId,
            'submissionText' => 'nota pelo caminho errado',
        ], $this->auth($instrutor))->assertStatus(403);

        $this->putJson("/api/exercise-submissions/{$subId}/grade", [
            'score' => 85,
            'feedback' => 'Bom trabalho.',
            'status' => 'approved',
        ], $this->auth($instrutor))
            ->assertOk()
            ->assertJsonPath('score', 85)
            ->assertJsonPath('status', 'approved')
            // gradedBy vem do token: o cliente mandava um nome fixo, que era ignorado.
            ->assertJsonPath('gradedBy', 'Gestor de Conteúdos');

        // O aluno vê a própria nota.
        $minhas = $this->getJson('/api/exercise-submissions', $this->auth($aluno['token']))->assertOk()->json();
        $this->assertCount(1, $minhas);
        $this->assertSame(85, $minhas[0]['score']);
    }

    public function test_aluno_nao_corrige_a_propria_entrega(): void
    {
        $instrutor = $this->staffToken('instructor');
        $courseId = $this->cursoDoInstrutor();
        $exId = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercises', [
            'id' => $exId, 'courseId' => $courseId, 'title' => 'x',
            'description' => 'd', 'instructions' => 'i', 'maxPoints' => 100,
        ], $this->auth($instrutor))->assertStatus(201);

        $aluno = $this->makeStudent('Aluno Autonota');
        DB::table('AdmissionRequest')->insert([
            'id' => 'adm-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $courseId,
            'status' => 'approved',
            'submittedAt' => '01/09/2026',
        ]);
        $subId = $this->postJson('/api/exercise-submissions', [
            'exerciseId' => $exId,
            'submissionText' => 'texto',
        ], $this->auth($aluno['token']))->json('id');

        $this->putJson("/api/exercise-submissions/{$subId}/grade", [
            'score' => 100, 'feedback' => 'me dei 100', 'status' => 'approved',
        ], $this->auth($aluno['token']))->assertStatus(403);
    }

    public function test_nota_fora_do_intervalo_e_status_invalido_sao_recusados(): void
    {
        $instrutor = $this->staffToken('instructor');

        $this->putJson('/api/exercise-submissions/submission-1/grade', [
            'score' => -1, 'feedback' => 'x', 'status' => 'approved',
        ], $this->auth($instrutor))->assertStatus(400);

        $this->putJson('/api/exercise-submissions/submission-1/grade', [
            'score' => 10, 'feedback' => 'x', 'status' => 'talvez',
        ], $this->auth($instrutor))->assertStatus(400);
    }
}
