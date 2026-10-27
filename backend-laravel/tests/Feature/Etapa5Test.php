<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Etapa 5 — módulos restantes (learning, mensagens, solicitações, settings, auditoria,
 * telemetria, export). Cobre os comportamentos-chave e os casos-espelho dos testes do Node.
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class Etapa5Test extends TestCase
{
    use DatabaseTransactions;

    private function token(string $role): string
    {
        $u = DB::table('usuario')->where('papel', $role)->where('status', 'active')->first(['id', 'nome', 'papel']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($u->id, $u->nome, $u->papel);
    }

    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer $token"];
    }

    // ---------- Export ----------

    public function test_admin_exports_students_without_password_hash(): void
    {
        $res = $this->getJson('/api/exportar/students', $this->auth($this->token('admin')));
        $res->assertOk()->assertJsonPath('dataset', 'students');
        $data = $res->json('dados');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('senha_hash', $data[0] ?? []);
    }

    public function test_student_cannot_export(): void
    {
        $this->getJson('/api/exportar/students', $this->auth($this->token('student')))->assertStatus(403);
    }

    public function test_instructor_cannot_export(): void
    {
        $this->getJson('/api/exportar/students', $this->auth($this->token('instructor')))->assertStatus(403);
    }

    public function test_export_courses_and_progress_shapes(): void
    {
        $admin = $this->auth($this->token('admin'));
        $courses = $this->getJson('/api/exportar/courses', $admin)->assertOk()->json('dados');
        $this->assertArrayHasKey('frequencia_minima', $courses[0]);
        $this->assertArrayHasKey('area_tematica', $courses[0]);

        // progress inclui as FKs (hardening).
        $prog = $this->getJson('/api/exportar/progress', $admin)->assertOk()->json('dados');
        if (! empty($prog)) {
            $this->assertArrayHasKey('usuario_id', $prog[0]);
            $this->assertArrayHasKey('matricula_id', $prog[0]);
        }
    }

    public function test_invalid_export_dataset_is_bad_request(): void
    {
        $this->getJson('/api/exportar/senhas', $this->auth($this->token('admin')))
            ->assertStatus(400)->assertJsonPath('code', 'BAD_REQUEST');
    }

    // ---------- Auditoria x Telemetria ----------

    public function test_security_logs_have_no_post_route(): void
    {
        // Auditoria é gravada só pelo servidor — não existe POST.
        $this->postJson('/api/logs-seguranca', ['x' => 1], $this->auth($this->token('admin')))
            ->assertStatus(405);
    }

    public function test_security_logs_list_is_admin_only(): void
    {
        $this->getJson('/api/logs-seguranca', $this->auth($this->token('admin')))->assertOk()
            ->assertJsonStructure(['items', 'pagination']);
        $this->getJson('/api/logs-seguranca', $this->auth($this->token('student')))->assertStatus(403);
    }

    public function test_telemetry_accepts_anonymous_and_read_is_admin_only(): void
    {
        // POST anônimo (sem token) é aceito.
        $this->postJson('/api/telemetria', ['acao' => 'nav', 'detalhes' => 'abriu painel'], ['Accept' => 'application/json'])
            ->assertStatus(201);
        // Leitura só admin.
        $this->getJson('/api/telemetria', ['Accept' => 'application/json'])->assertStatus(401);
        $this->getJson('/api/telemetria', $this->auth($this->token('admin')))->assertOk();
    }

    // ---------- Settings ----------

    public function test_settings_get_is_public_and_put_is_admin_only(): void
    {
        $this->getJson('/api/configuracoes-sistema')->assertOk();
        $this->putJson('/api/configuracoes-sistema', ['tema' => 'x'], $this->auth($this->token('student')))->assertStatus(403);
        // Chave real: estas configurações são públicas por contrato, e chave arbitrária
        // deixou de ser aceita (ver SettingsService::ALLOWED_KEYS). O objeto deste teste
        // é a autorização, não a liberdade de formato.
        $this->putJson('/api/configuracoes-sistema', ['allowGlobalChat' => true], $this->auth($this->token('admin')))
            ->assertOk()->assertJsonPath('allowGlobalChat', true);
    }

    // Chave fora da lista é recusada na ESCRITA: o GET desta rota é público, então
    // qualquer campo gravado aqui nasce visível a visitante anônimo.
    public function test_settings_rejects_unknown_key(): void
    {
        $this->putJson('/api/configuracoes-sistema', ['smtpPassword' => 'segredo'], $this->auth($this->token('admin')))
            ->assertStatus(400);

        $this->getJson('/api/configuracoes-sistema')->assertOk()->assertJsonMissingPath('smtpPassword');
    }

    // ---------- Learning ----------

    public function test_quizzes_list_requires_authentication(): void
    {
        // Segurança: o gabarito (indice_opcao_correta) não pode ser raspado anonimamente.
        $this->getJson('/api/avaliacoes')->assertStatus(401);

        $res = $this->getJson('/api/avaliacoes', $this->auth($this->token('student')));
        $res->assertOk();
        $this->assertIsArray($res->json());
    }

    public function test_quiz_submission_is_student_only(): void
    {
        $this->postJson('/api/tentativas-avaliacao', ['curso_id' => 'c', 'avaliacao_id' => 'q', 'percentual_nota' => 80, 'aprovado' => true], $this->auth($this->token('instructor')))
            ->assertStatus(403);
    }

    // ---------- Feature gate ----------

    public function test_forum_is_disabled_by_feature_flag(): void
    {
        // forum flag desligada por padrão -> 404 FEATURE_DISABLED (não só escondido na UI).
        $this->getJson('/api/forum', $this->auth($this->token('student')))
            ->assertStatus(404)->assertJsonPath('code', 'FEATURE_DISABLED');
    }
}
