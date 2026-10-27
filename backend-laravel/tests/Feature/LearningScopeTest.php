<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Escopo por curso em fórum, quizzes e exercícios práticos.
 *
 * O chat de aula (MessagingService) já filtrava por CourseAccess; estas três
 * listagens não recebiam o solicitante e devolviam o conteúdo de TODOS os cursos
 * para qualquer autenticado — incluindo as questões com gabarito de avaliações de
 * cursos que a pessoa nunca fez. A regra existia no projeto, faltava aqui.
 */
final class LearningScopeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        // Flag é chave de liga/desliga, não controle de acesso: o escopo tem de valer
        // com a funcionalidade ATIVA. O teste liga as duas explicitamente para não
        // depender do estado de config/features.php — que muda quando o produto decide
        // liberar um recurso (exercícios já foram liberados; fórum, ainda não).
        config(['features.forum' => true, 'features.atividadesPraticasAvancadas' => true]);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** @param array<int, mixed> $itens @return list<string> */
    private function cursos(array $itens): array
    {
        $ids = [];
        foreach ($itens as $item) {
            if (is_array($item) && is_string($item['curso_id'] ?? null)) {
                $ids[] = $item['curso_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Confere que a seed tem mais de um curso na tabela, senão o teste é vacuo. */
    private function assertSeedTemVariosCursos(string $tabela): void
    {
        $this->assertGreaterThan(
            1,
            DB::table($tabela)->distinct()->count('curso_id'),
            "Seed com um só curso em {$tabela}: o teste passaria sem provar escopo."
        );
    }

    public function test_student_without_enrollment_sees_no_forum_no_quizzes_no_exercises(): void
    {
        foreach (['mensagem_forum', 'avaliacao', 'exercicio_pratico'] as $tabela) {
            $this->assertSeedTemVariosCursos($tabela);
        }

        $auth = $this->auth($this->makeStudent()['token']);

        $this->assertSame([], $this->getJson('/api/forum', $auth)->assertOk()->json());
        $this->assertSame([], $this->getJson('/api/avaliacoes', $auth)->assertOk()->json());
        $this->assertSame([], $this->getJson('/api/exercicios', $auth)->assertOk()->json());
    }

    public function test_enrolled_student_sees_only_own_course(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);

        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'course-1'])
            ->assertOk();

        foreach (['/api/forum', '/api/avaliacoes', '/api/exercicios'] as $rota) {
            $itens = $this->getJson($rota, $auth)->assertOk()->json();
            $this->assertNotEmpty($itens, "Aluno matriculado ficou sem conteúdo em {$rota}.");
            $this->assertSame(['course-1'], $this->cursos($itens), "Vazou curso alheio em {$rota}.");
        }
    }

    public function test_quiz_answer_key_of_other_course_is_not_reachable(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'course-1'])
            ->assertOk();

        $quizzes = $this->getJson('/api/avaliacoes', $auth)->assertOk()->json();

        // De curso alheio não pode vir nada, e o gabarito NUNCA vem para o aluno (23/10/2026):
        // com ele, quem chamasse a API à mão tiraria 100% em qualquer prova do seu curso.
        foreach ($quizzes as $quiz) {
            $this->assertSame('course-1', $quiz['curso_id'] ?? null);
            foreach ($quiz['questoes'] as $questao) {
                $this->assertArrayHasKey('texto_questao', $questao);
                $this->assertArrayHasKey('opcoes', $questao);
                foreach (['indice_opcao_correta', 'explanation', 'mensagem_revisao'] as $segredo) {
                    $this->assertArrayNotHasKey($segredo, $questao, "{$segredo} vazou para o aluno");
                }
            }
        }
        $this->assertNotEmpty($quizzes[0]['questoes'] ?? []);
        $this->assertStringNotContainsString('indice_opcao_correta', (string) json_encode($quizzes));
    }

    public function test_student_cannot_post_in_forum_of_course_they_do_not_attend(): void
    {
        $auth = $this->auth($this->makeStudent()['token']);

        // curso_id vem do corpo: sem checagem, qualquer autenticado publicava em
        // qualquer turma. O chat de aula já barrava o caso equivalente.
        $this->postJson('/api/forum', ['curso_id' => 'course-1', 'texto' => 'Invasão de turma'], $auth)
            ->assertStatus(403);
    }

    public function test_student_can_post_in_forum_of_own_course(): void
    {
        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'course-1'])
            ->assertOk();

        $this->postJson('/api/forum', ['curso_id' => 'course-1', 'texto' => 'Dúvida legítima'], $auth)
            ->assertStatus(201)
            ->assertJsonPath('curso_id', 'course-1');
    }

    public function test_student_can_post_in_forum_of_extra_course(): void
    {
        // Curso extra da matrícula múltipla é matrícula: CourseAccess::canAccess
        // barrava com 403 porque não lia cursos_extras_ids.
        config(['features.matriculasMultiplas' => true]);

        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);
        $principalId = $this->anotherSeededCourseId('course-1');

        $this->withHeaders($this->auth($this->staffToken('admin')))
            ->putJson('/api/matriculas/'.$aluno['id'], ['pode_multimatricula' => true])
            ->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $principalId])
            ->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'course-1'])
            ->assertOk()
            ->assertJsonPath('enrollment.cursos_extras_ids', ['course-1']);

        $this->postJson('/api/forum', ['curso_id' => 'course-1', 'texto' => 'Dúvida no curso extra'], $auth)
            ->assertStatus(201)
            ->assertJsonPath('curso_id', 'course-1');
    }

    public function test_student_cannot_submit_quiz_of_course_they_do_not_attend(): void
    {
        $auth = $this->auth($this->makeStudent()['token']);

        $this->postJson('/api/tentativas-avaliacao', [
            'avaliacao_id' => 'quiz-1',
            'answers' => ['quiz-1-q1' => 0],
        ], $auth)->assertStatus(403);
    }

    public function test_admin_keeps_seeing_everything(): void
    {
        $auth = $this->auth($this->staffToken('admin'));

        foreach (['/api/forum', '/api/avaliacoes', '/api/exercicios'] as $rota) {
            $itens = $this->getJson($rota, $auth)->assertOk()->json();
            $this->assertGreaterThan(1, count($this->cursos($itens)), "Admin perdeu alcance em {$rota}.");
        }
    }

    public function test_instructor_sees_courses_they_teach(): void
    {
        $auth = $this->auth($this->staffToken('instructor'));

        // Instrutor não é admin: o alcance é o dos próprios cursos, e nunca mais que isso.
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $proprios = DB::table('curso')->where('instrutor_id', $instrutor_id)->pluck('id')->all();

        foreach (['/api/forum', '/api/avaliacoes', '/api/exercicios'] as $rota) {
            foreach ($this->cursos($this->getJson($rota, $auth)->assertOk()->json()) as $curso_id) {
                $this->assertContains($curso_id, $proprios, "Instrutor viu curso alheio em {$rota}.");
            }
        }
    }
}
