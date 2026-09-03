<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Histórico de tentativas de avaliação.
 *
 * Responder de novo INATIVAVA a tentativa anterior: o dado ficava no banco
 * (ADR 12) mas saía das listagens, e a tela só conseguia dizer "última
 * tentativa". Faltava a coluna `enviadoEm`, sem a qual não havia como afirmar
 * qual tentativa é a vigente — `submittedAt` é texto de exibição
 * ('03/09/2026 às 16:23') e ordena alfabeticamente: '01/12' antes de '03/09'.
 */
final class TentativasAvaliacaoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /**
     * Um quiz de uma questão num curso do aluno, para que a nota seja previsível.
     *
     * @return array{quiz: Quiz, aluno: array{id: string, name: string, cpf: string, token: string}, courseId: string}
     */
    private function cenario(): array
    {
        $courseId = $this->anySeededCourseId();
        $aluno = $this->makeStudent('Aluno Tentativas');
        // Matrícula pela rota real: responder avaliação exige pertencer ao curso.
        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])->assertOk();
        $this->flushHeaders();

        $quiz = Quiz::query()->create([
            'id' => 'quiz-tent-'.uniqid(),
            'courseId' => $courseId,
            'title' => 'Avaliação de Tentativas',
        ]);
        QuizQuestion::query()->create([
            'id' => 'q-tent-'.uniqid(),
            'quizId' => $quiz->id,
            'questionText' => 'Quanto é 2 + 2?',
            'options' => ['3', '4'],
            'correctOptionIndex' => 1,
        ]);

        return ['quiz' => $quiz, 'aluno' => $aluno, 'courseId' => $courseId];
    }

    public function test_a_segunda_tentativa_nao_inativa_a_primeira(): void
    {
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuizQuestion::query()->where('quizId', $c['quiz']->id)->value('id');

        // Erra, depois acerta.
        $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 0]], $aluno)
            ->assertCreated()
            ->assertJsonPath('scorePercent', 0);
        $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)
            ->assertCreated()
            ->assertJsonPath('scorePercent', 100);

        // As DUAS no ar: o histórico é o que a tela passa a poder mostrar.
        $linhas = DB::table('QuizSubmission')->where('quizId', $c['quiz']->id)->get();
        $this->assertCount(2, $linhas);
        foreach ($linhas as $linha) {
            $this->assertNull($linha->inativadoEm, 'A tentativa anterior voltou a ser inativada.');
            $this->assertNotNull($linha->enviadoEm, 'Tentativa gravada sem o eixo de ordenação.');
        }
    }

    public function test_a_listagem_devolve_a_tentativa_vigente_primeiro(): void
    {
        /*
         * Isto é contrato, não conveniência: há consumidor que lê a primeira
         * linha e a chama de nota do aluno — inclusive a declaração impressa.
         */
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuizQuestion::query()->where('quizId', $c['quiz']->id)->value('id');

        $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 0]], $aluno)->assertCreated();
        $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)->assertCreated();

        $notas = collect($this->getJson('/api/quiz-submissions', $aluno)->assertOk()->json())
            ->where('quizId', $c['quiz']->id)->pluck('scorePercent')->all();

        $this->assertSame([100, 0], $notas);
    }

    public function test_enviado_em_sai_com_fuso_para_a_tela_nao_deslocar_a_hora(): void
    {
        /*
         * COM o deslocamento de fuso. A aplicação roda em UTC
         * (`config('app.timezone')`) e o navegador do aluno não: sem o sufixo, o
         * JavaScript leria o valor como hora local e a tentativa recebida do
         * servidor pareceria três horas fora da que o cliente acabou de criar —
         * ordenando a vigente para trás.
         */
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuizQuestion::query()->where('quizId', $c['quiz']->id)->value('id');

        $enviadoEm = $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)
            ->assertCreated()->json('enviadoEm');

        $this->assertIsString($enviadoEm);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $enviadoEm,
            'Sem o deslocamento de fuso o navegador interpreta o instante errado.'
        );
    }

    public function test_tentativa_antiga_sem_data_reconhecivel_cai_para_o_fim(): void
    {
        // Linha cuja string de exibição a migration não soube interpretar fica
        // com `enviadoEm` nulo — e sem data o lugar dela é o fim, que é onde a
        // mais antiga pertence. Nenhuma data é inventada.
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuizQuestion::query()->where('quizId', $c['quiz']->id)->value('id');

        QuizSubmission::query()->create([
            'id' => 'sub-sem-data-'.uniqid(),
            'userId' => $c['aluno']['id'],
            'studentName' => $c['aluno']['name'],
            'courseId' => $c['courseId'],
            'quizId' => $c['quiz']->id,
            'scorePercent' => 42,
            'passed' => false,
            'submittedAt' => 'ontem de manhã',
            'enviadoEm' => null,
        ]);
        $this->postJson('/api/quiz-submissions', ['quizId' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)->assertCreated();

        $notas = collect($this->getJson('/api/quiz-submissions', $aluno)->assertOk()->json())
            ->where('quizId', $c['quiz']->id)->pluck('scorePercent')->all();

        $this->assertSame([100, 42], $notas);
    }
}
