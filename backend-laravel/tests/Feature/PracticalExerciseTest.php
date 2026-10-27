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
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $curso_id = DB::table('curso')->where('instrutor_id', $instrutor_id)->value('id');
        $this->assertNotNull($curso_id, 'Seed sem curso do instrutor — popular o MySQL de dev.');

        return $curso_id;
    }

    public function test_flag_desligada_devolve_404_em_toda_a_area(): void
    {
        // Flag é chave de liga/desliga: com ela off a API some inteira, e é isso
        // que fazia a tela parecer "funcionando" contra o localStorage.
        config(['features.atividadesPraticasAvancadas' => false]);
        $token = $this->staffToken('admin');

        $this->getJson('/api/exercicios', $this->auth($token))->assertStatus(404);
        $this->getJson('/api/entregas-exercicio', $this->auth($token))->assertStatus(404);
    }

    public function test_instrutor_lanca_edita_e_remove_exercicio_do_proprio_curso(): void
    {
        $token = $this->staffToken('instructor');
        $curso_id = $this->cursoDoInstrutor();
        $id = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercicios', [
            'id' => $id,
            'curso_id' => $curso_id,
            'titulo' => 'Análise de heurísticas',
            'descricao' => 'Objetivo da atividade.',
            'instrucoes' => 'Entregue um relatório de 500 palavras.',
            'pontuacao_maxima' => 80,
            'prazo_texto' => '30/09/2026',
        ], $this->auth($token))->assertStatus(201)->assertJsonPath('pontuacao_maxima', 80);

        // PUT /exercises/{id} é a rota de edição. O cliente mandava POST na rota
        // de criação e a edição simplesmente não acontecia.
        $this->putJson("/api/exercicios/{$id}", [
            'titulo' => 'Análise de heurísticas (revisada)',
            'pontuacao_maxima' => 90,
        ], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('titulo', 'Análise de heurísticas (revisada)')
            ->assertJsonPath('pontuacao_maxima', 90);

        $this->deleteJson("/api/exercicios/{$id}", [], $this->auth($token))->assertOk();
        // ADR 12: o exercício é inativado, não apagado — com a exclusão física
        // o CASCADE de EntregaExercicio.exercicio_id levava embora todo trabalho
        // entregue e corrigido.
        $this->assertDatabaseHas('exercicio_pratico', ['id' => $id]);
        $this->assertNotNull(DB::table('exercicio_pratico')->where('id', $id)->value('inativado_em'));
    }

    public function test_prazo_em_texto_livre_e_recusado(): void
    {
        $token = $this->staffToken('instructor');

        $this->postJson('/api/exercicios', [
            'curso_id' => $this->cursoDoInstrutor(),
            'titulo' => 'Com prazo inválido',
            'descricao' => 'd',
            'instrucoes' => 'i',
            'pontuacao_maxima' => 100,
            'prazo_texto' => 'julho de 2026',
        ], $this->auth($token))->assertStatus(400);
    }

    public function test_exercicio_pode_nascer_sem_prazo(): void
    {
        $token = $this->staffToken('instructor');

        $this->postJson('/api/exercicios', [
            'curso_id' => $this->cursoDoInstrutor(),
            'titulo' => 'Sem prazo',
            'descricao' => 'd',
            'instrucoes' => 'i',
            'pontuacao_maxima' => 100,
        ], $this->auth($token))->assertStatus(201)->assertJsonPath('prazo_texto', null);
    }

    public function test_aluno_nao_lanca_exercicio(): void
    {
        $aluno = $this->makeStudent('Aluno Exercicio');

        $this->postJson('/api/exercicios', [
            'curso_id' => $this->cursoDoInstrutor(),
            'titulo' => 'Tentativa',
            'descricao' => 'd',
            'instrucoes' => 'i',
            'pontuacao_maxima' => 100,
        ], $this->auth($aluno['token']))->assertStatus(403);
    }

    public function test_entrega_e_correcao_pelas_rotas_certas(): void
    {
        $instrutor = $this->staffToken('instructor');
        $curso_id = $this->cursoDoInstrutor();
        $exId = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercicios', [
            'id' => $exId,
            'curso_id' => $curso_id,
            'titulo' => 'Entrega e correção',
            'descricao' => 'd',
            'instrucoes' => 'i',
            'pontuacao_maxima' => 100,
        ], $this->auth($instrutor))->assertStatus(201);

        $aluno = $this->makeStudent('Aluno Entrega');
        DB::table('solicitacao_ingresso')->insert([
            'id' => 'adm-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso_id,
            'status' => 'approved',
            'enviado_em_texto' => '01/09/2026',
        ]);

        $entrega = $this->postJson('/api/entregas-exercicio', [
            'exercicio_id' => $exId,
            'texto_entrega' => 'Minha análise das heurísticas.',
        ], $this->auth($aluno['token']))->assertStatus(201);

        $subId = $entrega->json('id');
        // `enviado_em_texto` e `status` são do servidor: data de entrega é registro
        // acadêmico, não o relógio do navegador do aluno.
        $entrega->assertJsonPath('status', 'pending');
        $this->assertNotEmpty($entrega->json('enviado_em_texto'));

        // Reenviar atualiza a MESMA entrega, não cria uma segunda.
        $this->postJson('/api/entregas-exercicio', [
            'exercicio_id' => $exId,
            'texto_entrega' => 'Versão revisada.',
        ], $this->auth($aluno['token']))->assertStatus(201)->assertJsonPath('id', $subId);
        $this->assertSame(1, DB::table('entrega_exercicio')->where('exercicio_id', $exId)->count());

        // A rota de correção é PUT /{id}/grade. O cliente lançava nota com POST em
        // /exercise-submissions — que é `role:student` — e recebia 403 em silêncio.
        $this->postJson('/api/entregas-exercicio', [
            'exercicio_id' => $exId,
            'texto_entrega' => 'nota pelo caminho errado',
        ], $this->auth($instrutor))->assertStatus(403);

        $this->putJson("/api/entregas-exercicio/{$subId}/corrigir", [
            'nota' => 85,
            'devolutiva' => 'Bom trabalho.',
            'status' => 'approved',
        ], $this->auth($instrutor))
            ->assertOk()
            ->assertJsonPath('nota', 85)
            ->assertJsonPath('status', 'approved')
            // corrigido_por vem do token: o cliente mandava um nome fixo, que era ignorado.
            ->assertJsonPath('corrigido_por', 'Gestor de Conteúdos');

        // O aluno vê a própria nota.
        $minhas = $this->getJson('/api/entregas-exercicio', $this->auth($aluno['token']))->assertOk()->json();
        $this->assertCount(1, $minhas);
        $this->assertSame(85, $minhas[0]['nota']);
    }

    public function test_aluno_nao_corrige_a_propria_entrega(): void
    {
        $instrutor = $this->staffToken('instructor');
        $curso_id = $this->cursoDoInstrutor();
        $exId = 'exercise-teste-'.uniqid();

        $this->postJson('/api/exercicios', [
            'id' => $exId, 'curso_id' => $curso_id, 'titulo' => 'x',
            'descricao' => 'd', 'instrucoes' => 'i', 'pontuacao_maxima' => 100,
        ], $this->auth($instrutor))->assertStatus(201);

        $aluno = $this->makeStudent('Aluno Autonota');
        DB::table('solicitacao_ingresso')->insert([
            'id' => 'adm-'.uniqid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_id' => $curso_id,
            'status' => 'approved',
            'enviado_em_texto' => '01/09/2026',
        ]);
        $subId = $this->postJson('/api/entregas-exercicio', [
            'exercicio_id' => $exId,
            'texto_entrega' => 'texto',
        ], $this->auth($aluno['token']))->json('id');

        $this->putJson("/api/entregas-exercicio/{$subId}/corrigir", [
            'nota' => 100, 'devolutiva' => 'me dei 100', 'status' => 'approved',
        ], $this->auth($aluno['token']))->assertStatus(403);
    }

    public function test_nota_fora_do_intervalo_e_status_invalido_sao_recusados(): void
    {
        $instrutor = $this->staffToken('instructor');

        $this->putJson('/api/entregas-exercicio/submission-1/corrigir', [
            'nota' => -1, 'devolutiva' => 'x', 'status' => 'approved',
        ], $this->auth($instrutor))->assertStatus(400);

        $this->putJson('/api/entregas-exercicio/submission-1/corrigir', [
            'nota' => 10, 'devolutiva' => 'x', 'status' => 'talvez',
        ], $this->auth($instrutor))->assertStatus(400);
    }
}
