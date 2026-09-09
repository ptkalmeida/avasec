<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Frequência não basta quando o curso avalia.
 *
 * Por que esta regra existe, e por que o teste vive no backend: quando concluir
 * aula passou a ser automático ao avançar, quem clicasse "Próxima aula" até o
 * fim atingia o mínimo de frequência e **o certificado saía sozinho, sem
 * responder uma única questão**. O cliente nunca decide isso — o servidor
 * recalcula — então a regra tem de estar aqui, e não na tela.
 *
 * Decisão da coordenação (09/09/2026): curso com avaliação exige TODAS as
 * avaliações ativas APROVADAS; curso sem avaliação segue só pela frequência.
 */
final class CertificadoExigeAvaliacaoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /**
     * Curso de uma aula, com a aula concluída: frequência 100%, o único critério
     * que existia antes desta regra.
     *
     * @return array{courseId:string, student:array<string, mixed>}
     */
    private function cursoComFrequenciaCheia(string $nomeDoAluno): array
    {
        $student = $this->makeStudent($nomeDoAluno);
        $courseId = 'course-aval-'.uniqid();
        $lessonId = 'lesson-aval-'.uniqid();

        DB::table('Course')->insert([
            'id' => $courseId, 'title' => 'Curso Com Avaliacao', 'description' => 'desc',
            'category' => 'x', 'thumbnail' => 't', 'instructorName' => 'Gestor de Conteúdos',
        ]);
        DB::table('Lesson')->insert([
            'id' => $lessonId, 'courseId' => $courseId, 'title' => 'A1',
            'duration' => '5min', 'lesson_order' => 0,
        ]);
        DB::table('StudentProgress')->insert([
            'id' => 'prog-aval-'.uniqid(), 'studentName' => $student['name'], 'userId' => $student['id'],
            'courseId' => $courseId, 'completedLessons' => json_encode([$lessonId]),
            'attendedLiveSessions' => json_encode([]),
        ]);

        return ['courseId' => $courseId, 'student' => $student];
    }

    private function criarQuiz(string $courseId, string $titulo, int $status = Visibilidade::PUBLICADO, bool $inativo = false): string
    {
        $quizId = 'quiz-aval-'.uniqid();
        DB::table('Quiz')->insert([
            'id' => $quizId, 'courseId' => $courseId, 'title' => $titulo, 'status' => $status,
            'inativadoEm' => $inativo ? now()->toDateTimeString() : null,
        ]);

        return $quizId;
    }

    /** @param array<string, mixed> $student */
    private function enviarTentativa(array $student, string $courseId, string $quizId, bool $aprovado): void
    {
        DB::table('QuizSubmission')->insert([
            'id' => 'sub-aval-'.uniqid(),
            'studentName' => $student['name'], 'userId' => $student['id'],
            'courseId' => $courseId, 'quizId' => $quizId,
            'scorePercent' => $aprovado ? 90.0 : 20.0,
            'passed' => $aprovado,
            'submittedAt' => '09/09/2026 às 10:00',
            'enviadoEm' => now()->toDateTimeString(),
        ]);
    }

    /** @param array<string, mixed> $student */
    private function pedirCertificado(array $student, string $courseId): TestResponse
    {
        $r = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificates', ['courseId' => $courseId]);
        $this->flushHeaders();

        return $r;
    }

    public function test_curso_sem_avaliacao_continua_saindo_pela_frequencia(): void
    {
        /*
         * A regra nova não pode travar o que funcionava. Curso sem avaliação não
         * tem nada a exigir além da presença — é o que ele mede.
         */
        $c = $this->cursoComFrequenciaCheia('Cert Sem Prova');

        $this->pedirCertificado($c['student'], $c['courseId'])
            ->assertStatus(201)
            ->assertJsonPath('attendancePercent', 100);
    }

    public function test_frequencia_cheia_sem_fazer_a_prova_nao_gera_certificado(): void
    {
        // O defeito exato que a regra veio fechar: clicar "Próxima aula" até o fim.
        $c = $this->cursoComFrequenciaCheia('Cert So Clicou');
        $this->criarQuiz($c['courseId'], 'Avaliação Final');

        $this->pedirCertificado($c['student'], $c['courseId'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Falta ser aprovado na avaliação "Avaliação Final" para a emissão do certificado.');

        $this->assertSame(0, DB::table('Certificate')->where('userId', $c['student']['id'])->count());
    }

    public function test_prova_reprovada_nao_gera_certificado(): void
    {
        // A decisão foi "aprovada", não "respondida": zerar a prova não conclui.
        $c = $this->cursoComFrequenciaCheia('Cert Reprovado');
        $quiz = $this->criarQuiz($c['courseId'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['courseId'], $quiz, false);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(403);
    }

    public function test_prova_aprovada_gera_certificado(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Aprovado');
        $quiz = $this->criarQuiz($c['courseId'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['courseId'], $quiz, true);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(201);
    }

    public function test_todas_as_avaliacoes_sao_exigidas_e_a_mensagem_diz_quais_faltam(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Parcial');
        $q1 = $this->criarQuiz($c['courseId'], 'Prova 1');
        $this->criarQuiz($c['courseId'], 'Prova 2');
        $this->criarQuiz($c['courseId'], 'Prova 3');
        $this->enviarTentativa($c['student'], $c['courseId'], $q1, true);

        $this->pedirCertificado($c['student'], $c['courseId'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Faltam 2 avaliações aprovadas para a emissão do certificado: Prova 2, Prova 3.');
    }

    public function test_avaliacao_inativada_nao_trava_o_certificado(): void
    {
        /*
         * Tirar uma prova do ar não pode congelar o certificado de todo mundo que
         * já cumpriu o curso. A prova continua no banco (ADR 12) e deixa de ser
         * exigida.
         */
        $c = $this->cursoComFrequenciaCheia('Cert Prova Fora');
        $this->criarQuiz($c['courseId'], 'Prova Retirada', Visibilidade::PUBLICADO, true);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(201);
    }

    public function test_avaliacao_em_rascunho_nao_e_cobrada_do_aluno(): void
    {
        /*
         * O aluno não enxerga prova em rascunho; cobrá-la seria exigir o
         * impossível. A escada é consultada com o papel `student` fixo — se
         * usasse o papel de quem chama, o gestor emitindo em nome do aluno seria
         * cobrado por uma prova que só o gestor vê.
         */
        $c = $this->cursoComFrequenciaCheia('Cert Prova Rascunho');
        $this->criarQuiz($c['courseId'], 'Prova Em Elaboracao', Visibilidade::RASCUNHO);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(201);
    }

    public function test_aprovacao_em_tentativa_anterior_continua_valendo(): void
    {
        /*
         * A ADR 12 manda ACRESCENTAR tentativa, não sobrescrever. Então quem foi
         * aprovado e depois refez a prova e foi mal não perde a aprovação — ler
         * "a última tentativa" tiraria um certificado já conquistado.
         */
        $c = $this->cursoComFrequenciaCheia('Cert Duas Tentativas');
        $quiz = $this->criarQuiz($c['courseId'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['courseId'], $quiz, true);
        $this->enviarTentativa($c['student'], $c['courseId'], $quiz, false);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(201);
    }

    public function test_prova_aprovada_de_outro_curso_nao_conta(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Prova Alheia');
        $outro = $this->cursoComFrequenciaCheia('Cert Dono Da Outra');
        $quizDoCurso = $this->criarQuiz($c['courseId'], 'Avaliação Deste Curso');
        $quizAlheio = $this->criarQuiz($outro['courseId'], 'Avaliação De Outro');

        // Aprovado na prova do OUTRO curso, e nada no curso que está pedindo.
        $this->enviarTentativa($c['student'], $outro['courseId'], $quizAlheio, true);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(403);

        // E a aprovação certa libera.
        $this->enviarTentativa($c['student'], $c['courseId'], $quizDoCurso, true);
        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(201);
    }

    public function test_aprovacao_de_outro_aluno_nao_conta(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Sem Aprovacao');
        $colega = $this->makeStudent('Cert Colega Aprovado');
        $quiz = $this->criarQuiz($c['courseId'], 'Avaliação Final');
        $this->enviarTentativa($colega, $c['courseId'], $quiz, true);

        $this->pedirCertificado($c['student'], $c['courseId'])->assertStatus(403);
    }
}
