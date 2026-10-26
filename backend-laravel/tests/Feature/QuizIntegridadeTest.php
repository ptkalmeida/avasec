<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Duas coisas que o painel React garantia só no navegador, e que a API não.
 *
 * 1. O gabarito aponta para uma alternativa que EXISTE. A API aceitava
 *    `correctOptionIndex` qualquer: 2 alternativas e gabarito 9 era gravado, e a
 *    prova ficava impossível de acertar — bloqueando o certificado da turma.
 * 2. O id de uma questão só vale se ela já pertence à prova. O editor mandava
 *    `rascunho-N`, cujo contador recomeça a cada carregamento: ao criar o
 *    "Desafio 2", `rascunho-3` já existia no "Desafio 1" e a questão foi MOVIDA,
 *    com o texto sobrescrito. A original se perdeu. Também era um furo de
 *    autorização: quem soubesse o id de uma questão de outro curso a editava.
 */
final class QuizIntegridadeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

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

    private function curso(string $instructorId): Course
    {
        return Course::query()->create([
            'id' => 'course-integ-'.uniqid(),
            'title' => 'Curso De Integridade '.Str::random(4),
            'description' => 'Descrição longa o bastante para o validador aceitar.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $instructorId,
            'instructorName' => 'Instrutor',
        ]);
    }

    /** @return array<string, mixed> */
    private function questao(string $texto, int $correta = 0, ?string $id = null): array
    {
        return array_filter([
            'id' => $id,
            'questionText' => $texto,
            'options' => ['Primeira', 'Segunda', 'Terceira'],
            'correctOptionIndex' => $correta,
        ], static fn (mixed $v): bool => $v !== null);
    }

    public function test_gabarito_que_aponta_para_alternativa_inexistente_e_recusado(): void
    {
        $eu = $this->instrutor('Gestor Gabarito');
        $curso = $this->curso($eu['id']);

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Prova impossivel',
                'questions' => [$this->questao('Pergunta?', 9)],
            ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertFalse(Quiz::query()->where('courseId', $curso->id)->exists(), 'A prova impossível foi gravada.');
    }

    public function test_alternativas_repetidas_sao_recusadas(): void
    {
        $eu = $this->instrutor('Gestor Repetidas');
        $curso = $this->curso($eu['id']);

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Prova com duas certas',
                'questions' => [[
                    'questionText' => 'Pergunta?',
                    'options' => ['Igual', 'igual', 'Outra'],
                    'correctOptionIndex' => 0,
                ]],
            ])
            ->assertStatus(400);
    }

    public function test_prova_valida_continua_sendo_gravada(): void
    {
        $eu = $this->instrutor('Gestor Valida');
        $curso = $this->curso($eu['id']);

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Prova valida',
                'questions' => [$this->questao('Pergunta?', 2)],
            ])
            ->assertStatus(201);
    }

    /** O caso real: o contador do editor recomeçou e reusou `rascunho-3`. */
    public function test_id_de_questao_de_outra_prova_nao_a_move_nem_a_sobrescreve(): void
    {
        $eu = $this->instrutor('Gestor Duas Provas');
        $curso = $this->curso($eu['id']);

        $primeira = $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Primeira prova',
                'questions' => [$this->questao('Texto ORIGINAL da questao um.')],
            ])->assertStatus(201)->json();

        $idDaOriginal = (string) $primeira['questions'][0]['id'];

        // Segunda prova, reaproveitando o id da questão da primeira — como o
        // `rascunho-3` fez.
        $segunda = $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Segunda prova',
                'questions' => [$this->questao('Texto da questao da SEGUNDA prova.', 0, $idDaOriginal)],
            ])->assertStatus(201)->json();

        $original = QuizQuestion::query()->find($idDaOriginal);
        $this->assertNotNull($original);
        $this->assertSame($primeira['id'], $original->quizId, 'A questão foi MOVIDA para a outra prova.');
        $this->assertSame('Texto ORIGINAL da questao um.', $original->questionText, 'O texto original foi sobrescrito.');

        // A segunda prova ganhou questão PRÓPRIA, com id novo.
        $this->assertNotSame($idDaOriginal, $segunda['questions'][0]['id']);
        $this->assertSame('Texto da questao da SEGUNDA prova.', $segunda['questions'][0]['questionText']);
    }

    /** O mesmo furo, visto como autorização: instrutor B edita questão do A. */
    public function test_instrutor_nao_sobrescreve_questao_de_prova_de_outro_curso(): void
    {
        $a = $this->instrutor('Gestor A Integ');
        $b = $this->instrutor('Gestor B Integ');
        $cursoA = $this->curso($a['id']);
        $cursoB = $this->curso($b['id']);

        $provaA = $this->withHeader('Authorization', 'Bearer '.$a['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $cursoA->id,
                'title' => 'Prova do A',
                'questions' => [$this->questao('Questao do instrutor A.')],
            ])->assertStatus(201)->json();
        $idDoA = (string) $provaA['questions'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$b['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $cursoB->id,
                'title' => 'Prova do B',
                'questions' => [$this->questao('Tentativa do B de sobrescrever.', 0, $idDoA)],
            ])->assertStatus(201);

        $this->assertSame('Questao do instrutor A.', QuizQuestion::query()->find($idDoA)?->questionText);
    }

    /** Editar a PRÓPRIA prova mantém os ids: as respostas já dadas continuam apontando. */
    public function test_editar_a_propria_prova_mantem_o_id_das_questoes(): void
    {
        $eu = $this->instrutor('Gestor Edita Propria');
        $curso = $this->curso($eu['id']);

        $criada = $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'courseId' => $curso->id,
                'title' => 'Prova editavel',
                'questions' => [$this->questao('Antes da edicao.')],
            ])->assertStatus(201)->json();
        $idQuestao = (string) $criada['questions'][0]['id'];

        $this->withHeader('Authorization', 'Bearer '.$eu['token'])
            ->postJson('/api/quizzes', [
                'id' => $criada['id'],
                'courseId' => $curso->id,
                'title' => 'Prova editavel',
                'questions' => [$this->questao('Depois da edicao.', 1, $idQuestao)],
            ])->assertStatus(201);

        $this->assertSame(1, QuizQuestion::query()->where('quizId', $criada['id'])->count());
        $this->assertSame('Depois da edicao.', QuizQuestion::query()->find($idQuestao)?->questionText);
    }
}
