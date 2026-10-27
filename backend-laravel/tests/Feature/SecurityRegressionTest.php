<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Regressão de segurança — cada teste trava um exploit concreto encontrado no pentest
 * (e confirmado ao vivo) para que não reapareça. Requer o MySQL de dev populado.
 * Escritas em transação revertida.
 */
final class SecurityRegressionTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    // #4 — o gabarito (indice_opcao_correta) não pode ser raspado sem autenticação.
    public function test_listing_quizzes_requires_authentication(): void
    {
        $this->getJson('/api/avaliacoes')->assertStatus(401);
    }

    // #1 — aluno não lista usuários (vazava e-mails de todos, inclusive admins).
    public function test_student_cannot_list_users(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        $this->getJson('/api/autenticacao/usuarios?papel=admin', $auth)->assertStatus(403);
        $this->getJson('/api/autenticacao/usuarios?papel=instructor', $auth)->assertStatus(403);
        $this->getJson('/api/autenticacao/usuarios', $auth)->assertStatus(403);
    }

    // #1 — instrutor, mesmo pedindo role=admin, nunca recebe admins/instrutores.
    public function test_instructor_listing_users_never_returns_admins(): void
    {
        $token = $this->staffToken('instructor');
        $items = $this->getJson('/api/autenticacao/usuarios?papel=admin', $this->auth($token))
            ->assertOk()->json('items');
        $this->assertIsArray($items);
        foreach ($items as $u) {
            $role = is_array($u) ? ($u['papel'] ?? null) : null;
            $this->assertSame('student', $role, 'Instrutor recebeu usuário não-aluno.');
        }
    }

    // #3 — a nota do quiz é calculada no servidor; o cliente não a auto-declara.
    public function test_quiz_score_is_computed_server_side(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        // Matrícula no curso do quiz-1: responder avaliação de curso ao qual não se
        // pertence agora é 403, e o cenário sem matrícula nunca foi o objeto deste
        // teste — o objeto é a nota ser recalculada no servidor.
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => 'course-1'])
            ->assertOk();

        // Gabarito de quiz-1 (seed): q1=0, q2=1, q3=2.
        $correct = ['quiz-1-q1' => 0, 'quiz-1-q2' => 1, 'quiz-1-q3' => 2];
        $wrong = ['quiz-1-q1' => 1, 'quiz-1-q2' => 0, 'quiz-1-q3' => 0];

        // Mesmo mandando percentual_nota/aprovado no corpo, o servidor ignora e recalcula.
        $res = $this->postJson('/api/tentativas-avaliacao', [
            'avaliacao_id' => 'quiz-1',
            'answers' => $wrong,
            'percentual_nota' => 100,
            'aprovado' => true,
        ], $auth)->assertStatus(201);
        $res->assertJsonPath('percentual_nota', 0)->assertJsonPath('aprovado', false);

        $res2 = $this->postJson('/api/tentativas-avaliacao', [
            'avaliacao_id' => 'quiz-1',
            'answers' => $correct,
        ], $auth)->assertStatus(201);
        $res2->assertJsonPath('percentual_nota', 100)->assertJsonPath('aprovado', true);
    }

    // #2 — progresso só aceita IDs de aulas reais do curso; IDs inventados somem.
    public function test_progress_ignores_fake_lesson_ids(): void
    {
        $student = $this->makeStudent();
        $this->matricular($student, 'course-1');
        $auth = $this->auth($student['token']);

        $this->postJson('/api/progresso', [
            'curso_id' => 'course-1',
            'aulas_concluidas' => ['fake-1', 'fake-2', 'fake-3', 'fake-4', 'fake-5', 'lesson-1-1', 'lesson-1-1'],
        ], $auth)->assertOk();

        $progress = $this->getJson('/api/progresso', $auth)->assertOk()->json();
        $this->assertIsArray($progress);
        $completed = null;
        foreach ($progress as $row) {
            if (is_array($row) && ($row['curso_id'] ?? null) === 'course-1') {
                $completed = $row['aulas_concluidas'] ?? null;
                break;
            }
        }
        // Só o ID real e único sobrevive; os inventados e a duplicata são descartados.
        $this->assertSame(['lesson-1-1'], $completed);
    }

    // #2 — sem frequência real, a emissão de certificado é recusada (não forjável).
    public function test_certificate_forgery_is_blocked(): void
    {
        $student = $this->makeStudent();
        $this->matricular($student, 'course-1');
        $auth = $this->auth($student['token']);

        $this->postJson('/api/progresso', [
            'curso_id' => 'course-1',
            'aulas_concluidas' => ['x1', 'x2', 'x3', 'x4', 'x5', 'x6', 'x7', 'x8', 'x9', 'x10'],
        ], $auth)->assertOk();

        $this->postJson('/api/certificados', ['curso_id' => 'course-1'], $auth)->assertStatus(403);
    }

    // #5 — trocar a senha exige a senha atual (bloqueia takeover de sessão sequestrada).
    public function test_change_password_requires_current_password(): void
    {
        $student = $this->makeStudent();
        $auth = $this->auth($student['token']);

        // Sem currentPassword -> validação falha (400).
        $this->putJson('/api/autenticacao/senha', ['newPassword' => 'NovaSenha999'], $auth)->assertStatus(400);
        // Com senha atual errada -> 401.
        $this->putJson('/api/autenticacao/senha', [
            'currentPassword' => 'errada-total',
            'newPassword' => 'NovaSenha999',
        ], $auth)->assertStatus(401);
        // Com a senha atual correta (definida em makeStudent) -> 200.
        $this->putJson('/api/autenticacao/senha', [
            'currentPassword' => 'senha123456',
            'newPassword' => 'NovaSenha999',
        ], $auth)->assertOk();
    }

    // #8 — um token com role forjado não vale: o role é reconferido no banco.
    public function test_forged_admin_role_in_token_is_overridden_by_database(): void
    {
        $student = $this->makeStudent();
        // Token dizendo admin para um usuário que é student no banco.
        $forged = Jwt::issue($student['id'], $student['nome'], 'admin');

        // Rota admin-only: o RequireActiveAccount sobrescreve o role com 'student' -> 403.
        $this->putJson('/api/configuracoes-sistema', ['foo' => 'bar'], $this->auth($forged))
            ->assertStatus(403);
    }

    // #7 — instrutor só vê certificados dos cursos que leciona (não a plataforma toda).
    public function test_instructor_certificate_list_is_scoped_to_own_courses(): void
    {
        $token = $this->staffToken('instructor');
        $ownCourseIds = DB::table('curso')
            ->where('instrutor_id', DB::table('usuario')->where('papel', 'instructor')->value('id'))
            ->pluck('id')->all();

        $items = $this->getJson('/api/certificados', $this->auth($token))->assertOk()->json('items');
        $this->assertIsArray($items);
        foreach ($items as $cert) {
            $curso_id = is_array($cert) ? ($cert['curso_id'] ?? null) : null;
            $this->assertContains($curso_id, $ownCourseIds, 'Instrutor viu certificado de curso alheio.');
        }
    }

    // URL de curso só era validada como "string, max:2000": um instrutor podia gravar
    // javascript: em thumbnail, imagem_capa, documents[].url ou link_reuniao, e o valor
    // ia direto para um href na tela do aluno. O cookie é HttpOnly, mas viaja sozinho,
    // então o script agiria como a vítima.
    public function test_course_rejects_javascript_url_in_every_url_field(): void
    {
        $token = $this->staffToken('instructor');
        $base = [
            'titulo' => 'Curso XSS Teste',
            'descricao' => 'Descrição suficientemente longa para passar na validação.',
            'categoria' => 'Teste',
            'miniatura' => 'https://exemplo.com/capa.png',
        ];

        $variantes = [
            'miniatura' => ['miniatura' => 'javascript:alert(1)'],
            'imagem_capa' => ['imagem_capa' => 'javascript:alert(1)'],
            'documentos.url' => ['aulas' => [[
                'titulo' => 'Aula 1', 'duracao' => '10min', 'order' => 0,
                'documentos' => [['titulo' => 'Doc', 'tipo' => 'url', 'url' => 'javascript:alert(1)']],
            ]]],
            'link_reuniao' => ['sessoes_ao_vivo' => [[
                'titulo' => 'Encontro', 'agendado_para_texto' => '2026-09-01T10:00:00Z',
                'duracao_minutos' => 60, 'link_reuniao' => 'javascript:alert(1)',
            ]]],
        ];

        foreach ($variantes as $campo => $payload) {
            $this->postJson('/api/cursos', array_merge($base, $payload), $this->auth($token))
                ->assertStatus(400, "Campo {$campo} aceitou javascript:");
        }
    }

    // POST /api/certificados não tinha `role:` na rota, e o service não conferia
    // titularidade: um instrutor emitia certificado para curso que não leciona. A regra
    // já existia no download do PDF (CertificatePdfService), faltava na emissão.
    public function test_instructor_cannot_issue_certificate_for_course_they_do_not_teach(): void
    {
        $alheio = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')
            ->whereNotIn('id', DB::table('curso')->distinct()->select('instrutor_id'))
            ->first(['id', 'nome']);
        if ($alheio === null) {
            $this->markTestSkipped('Seed sem instrutor fora da titularidade dos cursos.');
        }

        $aluno = $this->makeStudent();
        $token = Jwt::issue($alheio->id, $alheio->nome, 'instructor');

        $this->postJson('/api/certificados', [
            'usuario_id' => $aluno['id'],
            'curso_id' => 'course-1',
        ], $this->auth($token))->assertStatus(403);
    }

    // A agenda dos próximos 30 dias (aba Calendário) só existe se agendado_para_texto for
    // data de verdade. Como texto livre chegavam "Hoje, às 19:30" e "Próxima Segunda,
    // às 20:00", impossíveis de ordenar ou filtrar.
    public function test_live_session_requires_real_date(): void
    {
        $token = $this->staffToken('instructor');
        $base = [
            'titulo' => 'Curso Agenda Teste',
            'descricao' => 'Descrição suficientemente longa para passar na validação.',
            'categoria' => 'Teste',
            'miniatura' => 'https://exemplo.com/capa.png',
        ];
        $sessao = static fn (string $quando): array => ['sessoes_ao_vivo' => [[
            'titulo' => 'Encontro', 'agendado_para_texto' => $quando,
            'duracao_minutos' => 60, 'link_reuniao' => 'https://meet.google.com/abc-defg-hij',
        ]]];

        foreach (['Hoje, às 19:30', 'Próxima Segunda, às 20:00', '15/09/2026', 'amanhã'] as $texto) {
            $this->postJson('/api/cursos', array_merge($base, $sessao($texto)), $this->auth($token))
                ->assertStatus(400, "Aceitou texto livre em agendado_para_texto: {$texto}");
        }

        $this->postJson('/api/cursos', array_merge($base, $sessao('2026-09-15T19:30')), $this->auth($token))
            ->assertStatus(201)
            ->assertJsonPath('sessoes_ao_vivo.0.agendado_para_texto', '2026-09-15T19:30');
    }

    public function test_webinar_requires_parseable_date_and_time(): void
    {
        // A flag está desligada no produto (decisão de 04/09/2026) e a rota
        // responde 404. O que este teste cobre é a VALIDAÇÃO da data, que precisa
        // continuar de pé para o dia em que webinar voltar ao ar — mesmo idioma
        // que CatalogPilotTest já usa.
        config(['features.eventosWebinars' => true]);
        $token = $this->staffToken('instructor');
        $base = [
            'titulo' => 'Webinar Agenda Teste',
            'descricao' => 'Descrição do webinar.',
            'link' => 'https://exemplo.com/inscricao',
        ];

        $this->postJson('/api/webinars', array_merge($base, ['data_texto' => 'junho de 2026', 'horario' => '19:00']), $this->auth($token))
            ->assertStatus(400);
        $this->postJson('/api/webinars', array_merge($base, ['data_texto' => '15/09/2026', 'horario' => '19h']), $this->auth($token))
            ->assertStatus(400);
        $this->postJson('/api/webinars', array_merge($base, ['data_texto' => '15/09/2026', 'horario' => '19:00']), $this->auth($token))
            ->assertStatus(201);
    }

    public function test_library_item_rejects_javascript_url(): void
    {
        $this->postJson('/api/biblioteca', [
            'titulo' => 'Item XSS',
            'tipo' => 'link',
            'categoria' => 'Teste',
            'url' => 'javascript:alert(1)',
        ], $this->auth($this->staffToken('instructor')))->assertStatus(400);
    }
}
