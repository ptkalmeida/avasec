<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\QuizSubmission;
use App\Models\StudentProgress;
use App\Models\User;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * ADR 12 — nada é apagado.
 *
 * Registro de pessoa, disciplina, conteúdo ou avaliação não sai do banco: sai do
 * ar. Estes testes existem porque a versão anterior apagava fisicamente em 14
 * pontos, com as chaves estrangeiras em ON DELETE CASCADE — apagar UM usuário
 * destruía notas, entregas corrigidas, progresso, matrícula e requerimentos, e
 * deixava o certificado apontando para ninguém.
 */
final class NadaEApagadoTest extends TestCase
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

    private function cursoDoInstrutor(): Course
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $curso = Course::query()->where('instructorId', $instructorId)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        return $curso;
    }

    public function test_excluir_aluno_preserva_o_historico_academico_inteiro(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Historico');

        // Histórico: uma nota, um progresso e um certificado.
        QuizSubmission::query()->create([
            'id' => 'sub-hist-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $curso->id,
            'quizId' => 'quiz-1',
            'scorePercent' => 90,
            'passed' => true,
            'submittedAt' => '03/09/2026 às 10:00',
        ]);
        StudentProgress::query()->create([
            'id' => 'prog-hist-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $curso->id,
            'completedLessons' => [],
            'attendedLiveSessions' => [],
        ]);
        Certificate::query()->create([
            'id' => 'cert-hist-'.uniqid(),
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'courseTitle' => $curso->title,
            'issueDate' => now(),
            'attendancePercent' => 100,
            'verificationHash' => 'AVA-TESTE-'.strtoupper(uniqid()),
        ]);

        $this->deleteJson("/api/auth/users/{$aluno['id']}", ['motivo' => 'Pedido da secretaria'], $this->auth($this->staffToken('admin')))
            ->assertOk();

        // A pessoa saiu do ar...
        $this->assertNotNull(DB::table('User')->where('id', $aluno['id'])->value('inativadoEm'));
        $this->assertNull(User::query()->find($aluno['id']), 'Usuário inativado não deve aparecer nas consultas padrão.');
        // ...mas continua no banco, com quem inativou e por quê.
        $registro = DB::table('User')->where('id', $aluno['id'])->first();
        $this->assertNotNull($registro, 'O usuário NÃO pode ser apagado do banco.');
        $this->assertSame('Pedido da secretaria', $registro->motivoInativacao);
        $this->assertNotNull($registro->inativadoPor, 'Falta o registro de quem inativou.');

        // E o histórico acadêmico — que o CASCADE destruía — está intacto.
        $this->assertSame(1, DB::table('QuizSubmission')->where('userId', $aluno['id'])->count(), 'Nota de avaliação foi perdida.');
        $this->assertSame(1, DB::table('StudentProgress')->where('userId', $aluno['id'])->count(), 'Progresso foi perdido.');
        $this->assertSame(1, DB::table('Certificate')->where('userId', $aluno['id'])->count(), 'Certificado foi perdido.');
        // O certificado continua ligado à pessoa (era SET NULL: ficava órfão).
        $this->assertNotNull(DB::table('Certificate')->where('userId', $aluno['id'])->value('userId'));
    }

    public function test_conta_inativada_nao_entra_mais(): void
    {
        // Preservar o registro não pode virar porta aberta: a conta tem de
        // deixar de autenticar.
        $aluno = $this->makeStudent('Aluno Bloqueado');
        $this->deleteJson("/api/auth/users/{$aluno['id']}", [], $this->auth($this->staffToken('admin')))->assertOk();

        $this->assertSame('blocked', DB::table('User')->where('id', $aluno['id'])->value('status'));

        // /api/courses é o catálogo PÚBLICO (jwt.optional): não serve de prova.
        // A rota tem de exigir conta ativa para o teste medir algo.
        $this->getJson('/api/quizzes', $this->auth($aluno['token']))->assertStatus(403);

        // E o login também é recusado. 401 e não 403 de propósito: a busca por
        // CPF não encontra a conta inativada, e responder "credenciais
        // inválidas" é o certo — dizer "esta conta foi inativada" revelaria a
        // quem tentou que aquele CPF existe na base.
        $this->postJson('/api/auth/login', [
            'cpf' => $aluno['cpf'],
            'password' => 'senha123456',
        ])->assertStatus(401);
    }

    public function test_curso_inativado_sai_do_catalogo_mas_fica_no_banco(): void
    {
        $curso = $this->cursoDoInstrutor();

        $this->deleteJson("/api/courses/{$curso->id}", ['motivo' => 'Turma encerrada'], $this->auth($this->staffToken('admin')))
            ->assertOk();

        $this->assertDatabaseHas('Course', ['id' => $curso->id]);
        $this->assertSame('Turma encerrada', DB::table('Course')->where('id', $curso->id)->value('motivoInativacao'));

        $catalogo = $this->getJson('/api/courses')->assertOk()->json();
        $ids = array_column(is_array($catalogo['items'] ?? null) ? $catalogo['items'] : $catalogo, 'id');
        $this->assertNotContains($curso->id, $ids, 'Curso inativado não pode aparecer no catálogo.');
    }

    public function test_a_trilha_de_auditoria_nao_pode_ser_apagada(): void
    {
        // Isto apagava SecurityLog inteiro — inclusive o registro de quem apagou.
        $antes = DB::table('SecurityLog')->count();
        $this->assertGreaterThan(0, $antes, 'Seed sem log de segurança para o teste valer.');

        $this->deleteJson('/api/security-logs', [], $this->auth($this->staffToken('admin')))->assertStatus(403);

        $this->assertSame($antes, DB::table('SecurityLog')->count());
    }

    public function test_avaliacao_inativada_preserva_as_notas_dos_alunos(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Nota');
        $token = $this->staffToken('instructor');

        $quiz = $this->postJson('/api/quizzes', [
            'courseId' => $curso->id,
            'title' => 'Avaliação a ser retirada',
            'questions' => [['questionText' => 'P?', 'options' => ['A', 'B'], 'correctOptionIndex' => 0]],
        ], $this->auth($token))->assertStatus(201)->json();

        QuizSubmission::query()->create([
            'id' => 'sub-nota-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $curso->id,
            'quizId' => $quiz['id'],
            'scorePercent' => 80,
            'passed' => true,
            'submittedAt' => '03/09/2026 às 11:00',
        ]);

        $this->deleteJson("/api/quizzes/{$quiz['id']}", [], $this->auth($token))->assertOk();

        // A avaliação saiu do ar; a nota que o aluno tirou continua no banco.
        $this->assertNotNull(DB::table('Quiz')->where('id', $quiz['id'])->value('inativadoEm'));
        $this->assertSame(
            1,
            DB::table('QuizSubmission')->where('quizId', $quiz['id'])->whereNull('inativadoEm')->count(),
            'A nota do aluno foi apagada junto com a avaliação.'
        );
    }

    public function test_certificado_revogado_nao_e_apagado(): void
    {
        $curso = $this->cursoDoInstrutor();
        $aluno = $this->makeStudent('Aluno Cert');
        $id = 'cert-rev-'.uniqid();

        Certificate::query()->create([
            'id' => $id,
            'studentName' => $aluno['name'],
            'userId' => $aluno['id'],
            'courseId' => $curso->id,
            'courseTitle' => $curso->title,
            'issueDate' => now(),
            'attendancePercent' => 100,
            'verificationHash' => 'AVA-REV-'.strtoupper(uniqid()),
        ]);

        $this->deleteJson("/api/certificates/{$id}", [], $this->auth($this->staffToken('admin')))->assertOk();

        // Certificado é documento: pode estar impresso ou anexado a um processo.
        $this->assertDatabaseHas('Certificate', ['id' => $id]);
        $this->assertNotNull(DB::table('Certificate')->where('id', $id)->value('inativadoEm'));
    }

    public function test_aula_retirada_e_readicionada_volta_sem_estourar_a_chave(): void
    {
        /*
         * Regressão específica da inativação: com o SoftDeletes, `find()` não
         * encontra a aula inativa. Sem `withTrashed()` no upsert, readicionar a
         * mesma aula tentaria INSERT e estouraria a chave primária.
         */
        $curso = Course::query()->with('lessons')->find($this->cursoDoInstrutor()->id);
        $this->assertNotNull($curso);
        $aula = $curso->lessons->first();
        $this->assertNotNull($aula, 'Curso do seed sem aula.');

        $paraPayload = fn ($lessons) => $lessons->map(fn ($l) => [
            'id' => $l->id, 'title' => $l->title, 'content' => $l->content ?? 'x',
            'duration' => $l->duration ?? '10 min', 'order' => $l->order ?? 1,
        ])->values()->all();

        $token = $this->auth($this->staffToken('instructor'));
        $base = ['title' => $curso->title, 'description' => $curso->description, 'category' => $curso->category];

        // Retira a primeira aula...
        $this->putJson("/api/courses/{$curso->id}",
            $base + ['lessons' => $paraPayload($curso->lessons->slice(1))], $token)->assertOk();
        $this->assertNotNull(DB::table('Lesson')->where('id', $aula->id)->value('inativadoEm'));

        // ...e devolve com o MESMO id.
        $this->putJson("/api/courses/{$curso->id}",
            $base + ['lessons' => $paraPayload($curso->lessons)], $token)->assertOk();

        $this->assertNull(
            DB::table('Lesson')->where('id', $aula->id)->value('inativadoEm'),
            'Aula readicionada tem de voltar ao ar, não duplicar.'
        );
        $this->assertSame(1, DB::table('Lesson')->where('id', $aula->id)->count());
    }

    public function test_escada_de_audiencia_do_status(): void
    {
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::nivelMaximoDoPapel('student'));
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::nivelMaximoDoPapel(null));
        $this->assertSame(Visibilidade::RESTRITO, Visibilidade::nivelMaximoDoPapel('instructor'));
        $this->assertSame(Visibilidade::RASCUNHO, Visibilidade::nivelMaximoDoPapel('admin'));

        // Quem vê o nível 1 vê tudo acima; o aluno não alcança restrito.
        $this->assertTrue(Visibilidade::papelAlcanca('student', Visibilidade::PUBLICADO));
        $this->assertFalse(Visibilidade::papelAlcanca('student', Visibilidade::RESTRITO));
        $this->assertTrue(Visibilidade::papelAlcanca('admin', Visibilidade::RASCUNHO));

        // Nível inventado pelo cliente cai em publicado, não vira coluna inválida.
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::normalizar(99));
        $this->assertSame(Visibilidade::PUBLICADO, Visibilidade::normalizar('abc'));
        $this->assertSame(Visibilidade::RASCUNHO, Visibilidade::normalizar('3'));
    }

    public function test_curso_restrito_nao_aparece_para_o_aluno(): void
    {
        $curso = $this->cursoDoInstrutor();
        DB::table('Course')->where('id', $curso->id)->update(['status' => Visibilidade::RESTRITO]);
        $aluno = $this->makeStudent('Aluno Restrito');

        $doAluno = $this->getJson('/api/courses', $this->auth($aluno['token']))->assertOk()->json();
        $ids = array_column(is_array($doAluno['items'] ?? null) ? $doAluno['items'] : $doAluno, 'id');
        $this->assertNotContains($curso->id, $ids);

        // Para o admin, continua visível.
        $doAdmin = $this->getJson('/api/courses', $this->auth($this->staffToken('admin')))->assertOk()->json();
        $idsAdmin = array_column(is_array($doAdmin['items'] ?? null) ? $doAdmin['items'] : $doAdmin, 'id');
        $this->assertContains($curso->id, $idsAdmin);
    }
}
