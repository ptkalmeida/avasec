<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\QuestaoAvaliacao;
use App\Models\TentativaAvaliacao;
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
 * tentativa". Faltava a coluna `enviado_em`, sem a qual não havia como afirmar
 * qual tentativa é a vigente — `enviado_em_texto` é texto de exibição
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
     * @return array{quiz: Avaliacao, aluno: array{id: string, name: string, cpf: string, token: string}, curso_id: string}
     */
    private function cenario(): array
    {
        $curso_id = $this->anySeededCourseId();
        $aluno = $this->makeStudent('Aluno Tentativas');
        // Matrícula pela rota real: responder avaliação exige pertencer ao curso.
        $this->withHeader('Authorization', "Bearer {$aluno['token']}")
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])->assertOk();
        $this->flushHeaders();

        $quiz = Avaliacao::query()->create([
            'id' => 'quiz-tent-'.uniqid(),
            'curso_id' => $curso_id,
            'titulo' => 'Avaliação de Tentativas',
        ]);
        QuestaoAvaliacao::query()->create([
            'id' => 'q-tent-'.uniqid(),
            'avaliacao_id' => $quiz->id,
            'texto_questao' => 'Quanto é 2 + 2?',
            'opcoes' => ['3', '4'],
            'indice_opcao_correta' => 1,
        ]);

        return ['quiz' => $quiz, 'aluno' => $aluno, 'curso_id' => $curso_id];
    }

    public function test_a_segunda_tentativa_nao_inativa_a_primeira(): void
    {
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuestaoAvaliacao::query()->where('avaliacao_id', $c['quiz']->id)->value('id');

        // Erra, depois acerta.
        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 0]], $aluno)
            ->assertCreated()
            ->assertJsonPath('percentual_nota', 0);
        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)
            ->assertCreated()
            ->assertJsonPath('percentual_nota', 100);

        // As DUAS no ar: o histórico é o que a tela passa a poder mostrar.
        $linhas = DB::table('tentativa_avaliacao')->where('avaliacao_id', $c['quiz']->id)->get();
        $this->assertCount(2, $linhas);
        foreach ($linhas as $linha) {
            $this->assertNull($linha->inativado_em, 'A tentativa anterior voltou a ser inativada.');
            $this->assertNotNull($linha->enviado_em, 'Tentativa gravada sem o eixo de ordenação.');
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
        $questaoId = (string) QuestaoAvaliacao::query()->where('avaliacao_id', $c['quiz']->id)->value('id');

        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 0]], $aluno)->assertCreated();
        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)->assertCreated();

        $notas = collect($this->getJson('/api/tentativas-avaliacao', $aluno)->assertOk()->json())
            ->where('avaliacao_id', $c['quiz']->id)->pluck('percentual_nota')->all();

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
        $questaoId = (string) QuestaoAvaliacao::query()->where('avaliacao_id', $c['quiz']->id)->value('id');

        $enviado_em = $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)
            ->assertCreated()->json('enviado_em');

        $this->assertIsString($enviado_em);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $enviado_em,
            'Sem o deslocamento de fuso o navegador interpreta o instante errado.'
        );
    }

    public function test_tentativa_antiga_sem_data_reconhecivel_cai_para_o_fim(): void
    {
        // Linha cuja string de exibição a migration não soube interpretar fica
        // com `enviado_em` nulo — e sem data o lugar dela é o fim, que é onde a
        // mais antiga pertence. Nenhuma data é inventada.
        $c = $this->cenario();
        $aluno = $this->auth($c['aluno']['token']);
        $questaoId = (string) QuestaoAvaliacao::query()->where('avaliacao_id', $c['quiz']->id)->value('id');

        TentativaAvaliacao::query()->create([
            'id' => 'sub-sem-data-'.uniqid(),
            'usuario_id' => $c['aluno']['id'],
            'nome_aluno' => $c['aluno']['nome'],
            'curso_id' => $c['curso_id'],
            'avaliacao_id' => $c['quiz']->id,
            'percentual_nota' => 42,
            'aprovado' => false,
            'enviado_em_texto' => 'ontem de manhã',
            'enviado_em' => null,
        ]);
        $this->postJson('/api/tentativas-avaliacao', ['avaliacao_id' => $c['quiz']->id, 'answers' => [$questaoId => 1]], $aluno)->assertCreated();

        $notas = collect($this->getJson('/api/tentativas-avaliacao', $aluno)->assertOk()->json())
            ->where('avaliacao_id', $c['quiz']->id)->pluck('percentual_nota')->all();

        $this->assertSame([100, 42], $notas);
    }
}
