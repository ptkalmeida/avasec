<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/inst/curso/{ref}/avaliacoes` — as provas do curso, em Blade.
 *
 * O que carrega o peso: que o gabarito impossível não seja gravado, que o curso
 * venha do endereço e nunca do formulário, que retirar não apague as respostas
 * dos alunos (ADR 12) e que um instrutor não alcance prova de outro.
 */
final class AvaliacoesDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return ['id' => $id, 'name' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    private function curso(string $instructorId, string $titulo = 'Curso Das Provas Teste'): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-prova-'.uniqid(),
            'title' => $titulo,
            'description' => 'Descrição longa o bastante para o validador aceitar.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $instructorId,
            'instructorName' => 'Instrutor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso->refresh();
    }

    private function prova(Course $curso, string $titulo = 'Prova De Teste', int $questoes = 2): Quiz
    {
        $quiz = Quiz::query()->create(['id' => 'quiz-adm-'.uniqid(), 'courseId' => $curso->id, 'title' => $titulo]);
        for ($i = 1; $i <= $questoes; $i++) {
            QuizQuestion::query()->create([
                'id' => $quiz->id.'-q'.$i,
                'quizId' => $quiz->id,
                'questionText' => "Pergunta numero {$i}?",
                'options' => ['Alfa', 'Beta', 'Gama'],
                'correctOptionIndex' => 1,
                'explanation' => "Explicacao {$i}",
                'reviewMessage' => "Dica {$i}",
                'recommendedModule' => "Modulo {$i}",
                'allowRetry' => false,
            ]);
        }

        return $quiz;
    }

    /** @return array<string, mixed> */
    private function corpoValido(string $titulo = 'Avaliacao Nova', array $sobrescreve = []): array
    {
        return array_merge([
            'titulo' => $titulo,
            'questoes' => [[
                'id' => '',
                'enunciado' => 'Qual e a capital do Brasil?',
                'alternativas' => ['Rio', 'Brasilia', 'Sao Paulo', ''],
                'correta' => 1,
                'explicacao' => 'Brasilia e a capital desde 1960.',
                'revisao' => 'Releia a aula sobre geografia.',
                'modulo' => 'Modulo 1',
            ]],
        ], $sobrescreve);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/inst/curso/qualquer/avaliacoes')->assertRedirect('/entrar');
    }

    public function test_a_lista_conta_alunos_e_nao_tentativas(): void
    {
        $eu = $this->instrutor('Gestor Conta Alunos');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso);

        $a = $this->makeStudent('Aluno Que Tentou Tres Vezes');
        $b = $this->makeStudent('Aluno Que Reprovou');
        foreach ([[$a, true], [$a, true], [$a, false], [$b, false]] as $n => [$aluno, $passou]) {
            QuizSubmission::query()->create([
                'id' => 'sub-adm-'.$n.'-'.uniqid(),
                'studentName' => $aluno['name'],
                'userId' => $aluno['id'],
                'courseId' => $curso->id,
                'quizId' => $quiz->id,
                'scorePercent' => $passou ? 100 : 0,
                'passed' => $passou,
                'submittedAt' => '21/10/2026 às 10:0'.$n,
                'enviadoEm' => now()->addSeconds($n),
            ]);
        }

        $html = (string) $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')->assertOk()->getContent();

        $this->assertStringContainsString('2 alunos responderam', $html);
        // `a` passou duas vezes e falhou uma: UM aprovado, e não dois.
        $this->assertStringContainsString('1 aprovado', $html);
        $this->assertStringNotContainsString('2 aprovados', $html);
        $this->assertStringNotContainsString('<div id="root">', $html);
    }

    public function test_curso_de_outro_instrutor_responde_404_em_tudo(): void
    {
        $eu = $this->instrutor('Gestor Um Provas');
        $outro = $this->instrutor('Gestor Dois Provas');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Das Provas');
        $quiz = $this->prova($alheio);
        $base = '/inst/curso/'.$alheio->slug.'/avaliacoes';

        $this->como($eu['token'])->get($base)->assertNotFound();
        $this->como($eu['token'])->get($base.'/nova')->assertNotFound();
        $this->como($eu['token'])->get($base.'/'.$quiz->id.'/editar')->assertNotFound();
        $this->como($eu['token'])->post($base, $this->corpoValido())->assertNotFound();
        $this->como($eu['token'])->post($base.'/'.$quiz->id, $this->corpoValido())->assertNotFound();
        $this->como($eu['token'])->post($base.'/'.$quiz->id.'/remover')->assertNotFound();

        $this->assertSame('Prova De Teste', Quiz::query()->whereKey($quiz->id)->value('title'));
    }

    public function test_criar_grava_a_prova_no_curso_do_endereco_e_audita(): void
    {
        $eu = $this->instrutor('Gestor Cria Prova');
        $curso = $this->curso($eu['id']);
        $antes = (int) DB::table('SecurityLog')->count();

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $this->corpoValido('Minha Prova Nova'))
            ->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $quiz = Quiz::query()->where('courseId', $curso->id)->where('title', 'Minha Prova Nova')->first();
        $this->assertNotNull($quiz, 'A prova não foi gravada.');

        $questao = QuizQuestion::query()->where('quizId', $quiz->id)->first();
        $this->assertNotNull($questao);
        // A alternativa vazia do FIM foi descartada; o gabarito segue na 2ª.
        $this->assertSame(['Rio', 'Brasilia', 'Sao Paulo'], $questao->options);
        $this->assertSame(1, $questao->correctOptionIndex);
        $this->assertSame('Brasilia e a capital desde 1960.', $questao->explanation);

        $this->assertGreaterThan($antes, (int) DB::table('SecurityLog')->count());
        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Criação de Avaliação')->exists());
    }

    /** O formulário não tem campo de curso; e se alguém mandar um, ele é ignorado. */
    public function test_o_curso_vem_do_endereco_e_nunca_do_formulario(): void
    {
        $eu = $this->instrutor('Gestor Curso Do Endereco');
        $meu = $this->curso($eu['id'], 'Curso Meu Das Provas');
        $outro = $this->instrutor('Gestor Outro Do Endereco');
        $alheio = $this->curso($outro['id'], 'Curso Alheio Do Endereco');

        $this->como($eu['token'])
            ->post('/inst/curso/'.$meu->slug.'/avaliacoes', $this->corpoValido('Tentativa De Plantar') + ['courseId' => $alheio->id]);

        $this->assertFalse(Quiz::query()->where('courseId', $alheio->id)->exists(), 'A prova foi plantada no curso alheio.');
        $this->assertTrue(Quiz::query()->where('courseId', $meu->id)->where('title', 'Tentativa De Plantar')->exists());
    }

    /** O defeito que a API tinha: gabarito apontando para alternativa que não existe. */
    public function test_gabarito_que_nao_existe_e_recusado_e_nada_e_gravado(): void
    {
        $eu = $this->instrutor('Gestor Gabarito Falso');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido('Prova Impossivel');
        $corpo['questoes'][0]['correta'] = 7;

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');

        $this->assertFalse(Quiz::query()->where('courseId', $curso->id)->exists());
    }

    public function test_alternativa_em_branco_no_meio_e_recusada(): void
    {
        $eu = $this->instrutor('Gestor Branco No Meio');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][0]['alternativas'] = ['Rio', '', 'Brasilia'];
        $corpo['questoes'][0]['correta'] = 2;

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');
    }

    public function test_alternativas_repetidas_sao_recusadas(): void
    {
        $eu = $this->instrutor('Gestor Repetidas Form');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][0]['alternativas'] = ['Igual', 'igual', 'Outra'];

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSessionHasErrors('questoes.0');
    }

    public function test_titulo_vazio_e_sem_questao_sao_recusados(): void
    {
        $eu = $this->instrutor('Gestor Sem Titulo');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', ['titulo' => '', 'questoes' => []])
            ->assertSessionHasErrors(['titulo', 'questoes']);
    }

    /** A mensagem diz QUAL questão tem o problema. */
    public function test_o_erro_diz_qual_questao_tem_o_problema(): void
    {
        $eu = $this->instrutor('Gestor Qual Questao');
        $curso = $this->curso($eu['id']);
        $corpo = $this->corpoValido();
        $corpo['questoes'][] = ['id' => '', 'enunciado' => '', 'alternativas' => ['a', 'b'], 'correta' => 0];

        $this->followingRedirects()
            ->como($eu['token'])
            ->from('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $corpo)
            ->assertSee('Questão 2: Escreva o enunciado da questão.', escape: false);
    }

    public function test_editar_mantem_o_id_das_questoes_e_preserva_a_regra_de_novas_tentativas(): void
    {
        $eu = $this->instrutor('Gestor Edita Prova');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso, 'Prova A Editar', 2);
        $idQ1 = $quiz->id.'-q1';

        $this->como($eu['token'])->post('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id, [
            'titulo' => 'Prova Editada',
            'questoes' => [[
                'id' => $idQ1,
                'enunciado' => 'Enunciado reescrito?',
                'alternativas' => ['Alfa', 'Beta', 'Gama'],
                'correta' => 2,
            ]],
        ])->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $this->assertSame('Prova Editada', Quiz::query()->whereKey($quiz->id)->value('title'));

        $q1 = QuizQuestion::query()->find($idQ1);
        $this->assertNotNull($q1, 'A questão perdeu o id.');
        $this->assertSame('Enunciado reescrito?', $q1->questionText);
        $this->assertSame(2, $q1->correctOptionIndex);
        // Não foi digitado de novo, e o que já estava gravado não é jogado fora.
        $this->assertFalse($q1->allowRetry);

        // A que saiu foi INATIVADA, e não apagada (ADR 12).
        $linha = DB::table('QuizQuestion')->where('id', $quiz->id.'-q2')->first();
        $this->assertNotNull($linha, 'A questão retirada foi APAGADA.');
        $this->assertNotNull($linha->inativadoEm);
    }

    public function test_o_formulario_de_edicao_traz_o_gabarito_e_avisa_de_quem_ja_respondeu(): void
    {
        $eu = $this->instrutor('Gestor Aviso Edicao');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso, 'Prova Com Respostas');
        $aluno = $this->makeStudent('Aluno Que Respondeu');
        QuizSubmission::query()->create([
            'id' => 'sub-aviso-'.uniqid(),
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'quizId' => $quiz->id,
            'scorePercent' => 100,
            'passed' => true,
            'submittedAt' => '21/10/2026 às 10:00',
            'enviadoEm' => now(),
        ]);

        $html = (string) $this->como($eu['token'])
            ->get('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id.'/editar')->assertOk()->getContent();

        $this->assertStringContainsString('1 aluno já respondeu', $html);
        $this->assertStringContainsString('não recalcula', $html);
        // O gabarito vai para quem o escreve.
        $this->assertStringContainsString('Pergunta numero 1?', $html);
        // E os rótulos dizem PARA QUEM cada texto aparece.
        $this->assertStringContainsString('aparece para quem ACERTOU', $html);
        $this->assertStringContainsString('aparece para quem ERROU', $html);
    }

    public function test_prova_nova_avisa_que_passa_a_ser_exigida_para_o_certificado(): void
    {
        $eu = $this->instrutor('Gestor Aviso Nova');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/nova')
            ->assertOk()
            ->assertSee('exigida para o certificado', escape: false);
    }

    public function test_prova_de_outro_curso_nao_abre_pelo_endereco_deste(): void
    {
        $eu = $this->instrutor('Gestor Prova Irma');
        $um = $this->curso($eu['id'], 'Curso Um Das Provas');
        $dois = $this->curso($eu['id'], 'Curso Dois Das Provas');
        $deDois = $this->prova($dois, 'Prova Do Curso Dois');

        $this->como($eu['token'])->get('/inst/curso/'.$um->slug.'/avaliacoes/'.$deDois->id.'/editar')->assertNotFound();
        $this->como($eu['token'])->post('/inst/curso/'.$um->slug.'/avaliacoes/'.$deDois->id.'/remover')->assertNotFound();
    }

    /** ADR 12: retirar a prova NÃO apaga as respostas dos alunos. */
    public function test_retirar_inativa_a_prova_e_preserva_as_respostas(): void
    {
        $eu = $this->instrutor('Gestor Retira Prova');
        $curso = $this->curso($eu['id']);
        $quiz = $this->prova($curso);
        $aluno = $this->makeStudent('Aluno Resposta Preservada');
        QuizSubmission::query()->create([
            'id' => 'sub-pres-'.uniqid(),
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'quizId' => $quiz->id,
            'scorePercent' => 80,
            'passed' => true,
            'submittedAt' => '21/10/2026 às 10:00',
            'enviadoEm' => now(),
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes/'.$quiz->id.'/remover')
            ->assertRedirect('/inst/curso/'.$curso->slug.'/avaliacoes');

        $linha = DB::table('Quiz')->where('id', $quiz->id)->first();
        $this->assertNotNull($linha, 'A prova foi APAGADA.');
        $this->assertNotNull($linha->inativadoEm);
        $this->assertSame(1, QuizSubmission::query()->where('quizId', $quiz->id)->count(), 'A resposta do aluno sumiu.');
        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Inativação de Avaliação')->exists());

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')
            ->assertDontSee('Prova De Teste', escape: false);
    }

    /** O ciclo inteiro: o gestor cria, o aluno responde e a prova que ele vê não traz o gabarito. */
    public function test_a_prova_criada_pelo_gestor_chega_ao_aluno_sem_gabarito(): void
    {
        $eu = $this->instrutor('Gestor Ciclo Completo');
        $curso = $this->curso($eu['id']);
        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/avaliacoes', $this->corpoValido('Prova Do Ciclo'));

        $aluno = $this->makeStudent('Aluno Ciclo Completo');
        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'enrolledCourseId' => $curso->id,
            'enrolledAt' => now(),
            'completedCourseIds' => [],
            'dropOutPenaltyUntil' => null,
            'canMultiEnroll' => false,
            'extraCourseIds' => [],
        ]);
        $quizId = (string) Quiz::query()->where('courseId', $curso->id)->value('id');

        $html = (string) $this->como($aluno['token'])
            ->get('/aluno/curso/'.$curso->slug.'/avaliacoes/'.$quizId)->assertOk()->getContent();

        $this->assertStringContainsString('Qual e a capital do Brasil?', $html);
        $this->assertStringNotContainsString('correctOptionIndex', $html);
        $this->assertStringNotContainsString('Brasilia e a capital desde 1960.', $html);
    }

    public function test_exercicios_desligado_o_servidor_explica(): void
    {
        $eu = $this->instrutor('Gestor Exercicios');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/exercicios')
            ->assertOk()
            ->assertDontSee('<div id="root">', false)
            ->assertSee('Esta seção não está disponível.', escape: false);
    }

    public function test_com_exercicios_ligado_a_tela_responde_501_ate_ser_construida(): void
    {
        config(['features.atividadesPraticasAvancadas' => true]);
        $eu = $this->instrutor('Gestor Exercicios Ligado');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/exercicios')->assertStatus(501);
    }

    public function test_sem_quiz_simples_tudo_responde_404(): void
    {
        config(['features.quizSimples' => false]);
        $eu = $this->instrutor('Gestor Sem Quiz');
        $curso = $this->curso($eu['id']);

        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes')->assertNotFound();
        $this->como($eu['token'])->get('/inst/curso/'.$curso->slug.'/avaliacoes/nova')->assertNotFound();
    }
}
