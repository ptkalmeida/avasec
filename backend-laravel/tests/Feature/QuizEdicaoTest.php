<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\Curso;
use App\Models\QuestaoAvaliacao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Edição de avaliação.
 *
 * Não havia rota de edição e a tela não tinha como editar: publicada a avaliação,
 * corrigir um enunciado exigia apagá-la — e apagar avaliação apaga as respostas
 * que os alunos já entregaram. A área nova de avaliações edita reusando
 * `POST /api/avaliacoes`, que é upsert pela chave `id`. Estes testes fixam esse
 * contrato, porque a tela agora depende dele.
 */
final class QuizEdicaoTest extends TestCase
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

    /** Curso do instrutor ativo do seed, e o token dele. */
    private function cursoDoInstrutor(): Curso
    {
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $curso = Curso::query()->where('instrutor_id', $instrutor_id)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        return $curso;
    }

    /** @param array<int, array<string, mixed>> $questoes */
    private function payload(string $curso_id, string $titulo, array $questoes, ?string $id = null): array
    {
        $corpo = ['curso_id' => $curso_id, 'titulo' => $titulo, 'questoes' => $questoes];

        return $id === null ? $corpo : ['id' => $id] + $corpo;
    }

    /** @return array<string, mixed> */
    private function questao(string $texto, array $opcoes = ['A', 'B'], int $correta = 0, ?string $id = null): array
    {
        $q = ['texto_questao' => $texto, 'opcoes' => $opcoes, 'indice_opcao_correta' => $correta];

        return $id === null ? $q : ['id' => $id] + $q;
    }

    public function test_reenviar_com_o_mesmo_id_edita_em_vez_de_duplicar(): void
    {
        $curso = $this->cursoDoInstrutor();
        $token = $this->staffToken('instructor');

        $criado = $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Título original', [$this->questao('Pergunta original')]),
            $this->auth($token)
        )->assertStatus(201)->json();

        $avaliacao_id = $criado['id'];
        $questaoId = $criado['questoes'][0]['id'];

        $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Título corrigido', [
                $this->questao('Pergunta corrigida', ['A', 'B', 'C'], 2, $questaoId),
            ], $avaliacao_id),
            $this->auth($token)
        )->assertSuccessful();

        // Uma avaliação, não duas.
        $this->assertSame(1, Avaliacao::query()->where('id', $avaliacao_id)->count());
        $this->assertSame('Título corrigido', Avaliacao::query()->find($avaliacao_id)?->titulo);

        // O id da questão sobrevive: é ele que liga a resposta do aluno ao item.
        $questoes = QuestaoAvaliacao::query()->where('avaliacao_id', $avaliacao_id)->get();
        $this->assertCount(1, $questoes);
        $this->assertSame($questaoId, $questoes[0]->id);
        $this->assertSame('Pergunta corrigida', $questoes[0]->texto_questao);
        $this->assertSame(2, $questoes[0]->indice_opcao_correta);
    }

    public function test_questao_removida_na_edicao_sai_e_a_nova_entra(): void
    {
        $curso = $this->cursoDoInstrutor();
        $token = $this->staffToken('instructor');

        $criado = $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Duas questões', [
                $this->questao('Fica'),
                $this->questao('Sai'),
            ]),
            $this->auth($token)
        )->assertStatus(201)->json();

        $avaliacao_id = $criado['id'];
        $idQueFica = $criado['questoes'][0]['id'];
        $idQueSai = $criado['questoes'][1]['id'];

        $editado = $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Duas questões', [
                $this->questao('Fica', ['A', 'B'], 0, $idQueFica),
                $this->questao('Nova'),
            ], $avaliacao_id),
            $this->auth($token)
        )->assertSuccessful()->json();

        $ids = array_column($editado['questoes'], 'id');
        $this->assertContains($idQueFica, $ids);
        $this->assertNotContains($idQueSai, $ids);
        $this->assertCount(2, $ids);
        // ADR 12: a questão retirada é inativada, não apagada — a resposta que
        // o aluno já deu continua apontando para um item que existe.
        $this->assertDatabaseHas('questao_avaliacao', ['id' => $idQueSai]);
        $this->assertNotNull(DB::table('questao_avaliacao')->where('id', $idQueSai)->value('inativado_em'));
    }

    public function test_instrutor_nao_edita_avaliacao_de_curso_alheio(): void
    {
        $curso = $this->cursoDoInstrutor();
        $token = $this->staffToken('instructor');

        // O seed tem todos os cursos com o mesmo responsável, então o cenário é
        // montado aqui: um curso de OUTRO instrutor (a transação do teste desfaz).
        $outroInstrutor = DB::table('usuario')->where('papel', 'instructor')
            ->where('id', '!=', $curso->instrutor_id)->value('id');
        $this->assertNotNull($outroInstrutor, 'Seed sem um segundo instrutor.');

        $alheio = Curso::query()->create([
            'id' => 'course-de-outro-'.uniqid(),
            'titulo' => 'Disciplina de outro responsável',
            'descricao' => 'd',
            'categoria' => 'Design',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $outroInstrutor,
            'nome_instrutor' => 'Outro Instrutor',
        ]);

        $criado = $this->postJson(
            '/api/avaliacoes',
            $this->payload($alheio->id, 'Do outro', [$this->questao('P?')]),
            $this->auth($this->staffToken('admin'))
        )->assertStatus(201)->json();

        // Editar avaliação alheia é recusado...
        $this->postJson(
            '/api/avaliacoes',
            $this->payload($alheio->id, 'Sequestrada', [$this->questao('P?')], $criado['id']),
            $this->auth($token)
        )->assertStatus(403);

        // ...e apontá-la para o próprio curso também não passa: seria roubar a
        // avaliação de outro responsável para dentro da própria disciplina.
        $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Sequestrada', [$this->questao('P?')], $criado['id']),
            $this->auth($token)
        )->assertStatus(403);

        $this->assertSame('Do outro', Avaliacao::query()->find($criado['id'])?->titulo);
    }

    public function test_aluno_nao_cria_nem_edita_avaliacao(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Sem Poder');

        $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Feita por aluno', [$this->questao('P?')]),
            $this->auth($aluno['token'])
        )->assertStatus(403);

        $this->assertDatabaseMissing('avaliacao', ['titulo' => 'Feita por aluno']);
    }

    public function test_avaliacao_sem_questao_e_recusada(): void
    {
        $curso = $this->cursoDoInstrutor();

        $this->postJson(
            '/api/avaliacoes',
            $this->payload($curso->id, 'Vazia', []),
            $this->auth($this->staffToken('instructor'))
        )->assertStatus(400);
    }
}
