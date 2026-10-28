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
     * @return array{curso_id:string, student:array<string, mixed>}
     */
    private function cursoComFrequenciaCheia(string $nomeDoAluno): array
    {
        $student = $this->makeStudent($nomeDoAluno);
        $curso_id = 'course-aval-'.uniqid();
        $aula_id = 'lesson-aval-'.uniqid();

        DB::table('curso')->insert([
            'id' => $curso_id, 'titulo' => 'Curso Com Avaliacao', 'descricao' => 'desc',
            'categoria' => 'x', 'miniatura' => 't', 'nome_instrutor' => 'Gestor de Conteúdos',
        ]);
        DB::table('aula')->insert([
            'id' => $aula_id, 'curso_id' => $curso_id, 'titulo' => 'A1',
            'duracao' => '5min', 'ordem' => 0,
        ]);
        $this->matricular($student, $curso_id);
        DB::table('progresso_aluno')->insert([
            'id' => 'prog-aval-'.uniqid(), 'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'],
            'curso_id' => $curso_id, 'aulas_concluidas' => json_encode([$aula_id]),
            'sessoes_ao_vivo_assistidas' => json_encode([]),
        ]);

        return ['curso_id' => $curso_id, 'student' => $student];
    }

    private function criarQuiz(string $curso_id, string $titulo, int $status = Visibilidade::PUBLICADO, bool $inativo = false): string
    {
        $avaliacao_id = 'quiz-aval-'.uniqid();
        DB::table('avaliacao')->insert([
            'id' => $avaliacao_id, 'curso_id' => $curso_id, 'titulo' => $titulo, 'status' => $status,
            'inativado_em' => $inativo ? now()->toDateTimeString() : null,
        ]);

        return $avaliacao_id;
    }

    /** @param array<string, mixed> $student */
    private function enviarTentativa(array $student, string $curso_id, string $avaliacao_id, bool $aprovado): void
    {
        DB::table('tentativa_avaliacao')->insert([
            'id' => 'sub-aval-'.uniqid(),
            'nome_aluno' => $student['nome'], 'usuario_id' => $student['id'],
            'curso_id' => $curso_id, 'avaliacao_id' => $avaliacao_id,
            'percentual_nota' => $aprovado ? 90.0 : 20.0,
            'aprovado' => $aprovado,
            'enviado_em_texto' => '09/09/2026 às 10:00',
            'enviado_em' => now()->toDateTimeString(),
        ]);
    }

    /** @param array<string, mixed> $student */
    private function pedirCertificado(array $student, string $curso_id): TestResponse
    {
        $r = $this->withHeader('Authorization', "Bearer {$student['token']}")
            ->postJson('/api/certificados', ['curso_id' => $curso_id]);
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

        $this->pedirCertificado($c['student'], $c['curso_id'])
            ->assertStatus(201)
            ->assertJsonPath('percentual_frequencia', 100);
    }

    public function test_frequencia_cheia_sem_fazer_a_prova_nao_gera_certificado(): void
    {
        // O defeito exato que a regra veio fechar: clicar "Próxima aula" até o fim.
        $c = $this->cursoComFrequenciaCheia('Cert So Clicou');
        $this->criarQuiz($c['curso_id'], 'Avaliação Final');

        $this->pedirCertificado($c['student'], $c['curso_id'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Falta ser aprovado na avaliação "Avaliação Final" para a emissão do certificado.');

        $this->assertSame(0, DB::table('certificado')->where('usuario_id', $c['student']['id'])->count());
    }

    public function test_prova_reprovada_nao_gera_certificado(): void
    {
        // A decisão foi "aprovada", não "respondida": zerar a prova não conclui.
        $c = $this->cursoComFrequenciaCheia('Cert Reprovado');
        $quiz = $this->criarQuiz($c['curso_id'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['curso_id'], $quiz, false);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(403);
    }

    public function test_prova_aprovada_gera_certificado(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Aprovado');
        $quiz = $this->criarQuiz($c['curso_id'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['curso_id'], $quiz, true);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(201);
    }

    public function test_todas_as_avaliacoes_sao_exigidas_e_a_mensagem_diz_quais_faltam(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Parcial');
        $q1 = $this->criarQuiz($c['curso_id'], 'Prova 1');
        $this->criarQuiz($c['curso_id'], 'Prova 2');
        $this->criarQuiz($c['curso_id'], 'Prova 3');
        $this->enviarTentativa($c['student'], $c['curso_id'], $q1, true);

        $this->pedirCertificado($c['student'], $c['curso_id'])
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
        $this->criarQuiz($c['curso_id'], 'Prova Retirada', Visibilidade::PUBLICADO, true);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(201);
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
        $this->criarQuiz($c['curso_id'], 'Prova Em Elaboracao', Visibilidade::RASCUNHO);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(201);
    }

    public function test_aprovacao_em_tentativa_anterior_continua_valendo(): void
    {
        /*
         * A ADR 12 manda ACRESCENTAR tentativa, não sobrescrever. Então quem foi
         * aprovado e depois refez a prova e foi mal não perde a aprovação — ler
         * "a última tentativa" tiraria um certificado já conquistado.
         */
        $c = $this->cursoComFrequenciaCheia('Cert Duas Tentativas');
        $quiz = $this->criarQuiz($c['curso_id'], 'Avaliação Final');
        $this->enviarTentativa($c['student'], $c['curso_id'], $quiz, true);
        $this->enviarTentativa($c['student'], $c['curso_id'], $quiz, false);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(201);
    }

    public function test_prova_aprovada_de_outro_curso_nao_conta(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Prova Alheia');
        $outro = $this->cursoComFrequenciaCheia('Cert Dono Da Outra');
        $quizDoCurso = $this->criarQuiz($c['curso_id'], 'Avaliação Deste Curso');
        $quizAlheio = $this->criarQuiz($outro['curso_id'], 'Avaliação De Outro');

        // Aprovado na prova do OUTRO curso, e nada no curso que está pedindo.
        $this->enviarTentativa($c['student'], $outro['curso_id'], $quizAlheio, true);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(403);

        // E a aprovação certa libera.
        $this->enviarTentativa($c['student'], $c['curso_id'], $quizDoCurso, true);
        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(201);
    }

    public function test_aprovacao_de_outro_aluno_nao_conta(): void
    {
        $c = $this->cursoComFrequenciaCheia('Cert Sem Aprovacao');
        $colega = $this->makeStudent('Cert Colega Aprovado');
        $quiz = $this->criarQuiz($c['curso_id'], 'Avaliação Final');
        $this->enviarTentativa($colega, $c['curso_id'], $quiz, true);

        $this->pedirCertificado($c['student'], $c['curso_id'])->assertStatus(403);
    }
}
