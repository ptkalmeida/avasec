<?php

declare(strict_types=1);

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Critérios de aceitação do módulo piloto (Etapa 1 da migração Node -> Laravel):
 * reproduz, no Laravel, o comportamento que tests/security.test.ts e a rota
 * catalogRoutes.ts garantem no Node para biblioteca/webinars.
 *
 * Requer o MySQL de dev de pé (npm run db:up) e populado (npm run db:seed) — os
 * testes usam os usuários demo do seed. Escritas rodam em transação revertida.
 */
final class CatalogPilotTest extends TestCase
{
    use DatabaseTransactions;

    private function userIdByRole(string $role): string
    {
        $user = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($user, "Seed sem usuário ativo com role '$role' — rode npm run db:seed.");

        return $user->id;
    }

    private function tokenForRole(string $role): string
    {
        $user = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($user, "Seed sem usuário ativo com role '$role'.");

        // Assina do mesmo jeito que o Node (HS256, mesmo segredo) — espelha o tokenFor()
        // dos testes do Node, sem passar pelo login real.
        return JWT::encode(
            ['sub' => $user->id, 'name' => $user->name, 'role' => $user->role, 'iat' => 1700000000, 'exp' => 1900000000],
            (string) config('app.jwt_secret'),
            'HS256',
        );
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    public function test_get_library_is_public_and_returns_json_array(): void
    {
        $response = $this->getJson('/api/library');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_post_library_without_token_is_rejected(): void
    {
        $response = $this->postJson('/api/library', [
            'title' => 'Material X',
            'type' => 'pdf',
            'category' => 'Design',
            'url' => 'https://example.com/x.pdf',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => true, 'code' => 'UNAUTHORIZED']);
    }

    public function test_post_library_as_student_is_forbidden(): void
    {
        $token = $this->tokenForRole('student');

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/library', [
            'title' => 'Material X',
            'type' => 'pdf',
            'category' => 'Design',
            'url' => 'https://example.com/x.pdf',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => true, 'code' => 'FORBIDDEN']);
    }

    public function test_post_library_as_admin_creates_item(): void
    {
        $token = $this->tokenForRole('admin');
        $id = 'lib-test-'.uniqid();

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/library', [
            'id' => $id,
            'title' => 'Material de Teste',
            'type' => 'link',
            'category' => 'Testes',
            'description' => 'criado pelo teste automatizado',
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson(['id' => $id, 'title' => 'Material de Teste', 'type' => 'link']);
        $this->assertDatabaseHas('LibraryItem', ['id' => $id]);
    }

    public function test_post_library_with_invalid_type_returns_validation_error(): void
    {
        $token = $this->tokenForRole('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/library', [
            'title' => 'X',
            'type' => 'exe', // não permitido: só pdf|video|link
            'category' => 'Y',
            'url' => 'z',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => true, 'code' => 'VALIDATION_ERROR']);
    }

    public function test_route_behind_a_disabled_feature_flag_returns_404(): void
    {
        // O objeto do teste é o FeatureGate: flag desligada some da API, não só do menu.
        // A flag é desligada AQUI de propósito — antes o teste dependia de
        // eventosWebinars estar false em config/features.php, então virava vermelho
        // quando o produto decidia ligar o recurso, sem nada ter quebrado.
        config(['features.eventosWebinars' => false]);

        $this->getJson('/api/webinars')
            ->assertStatus(404)
            ->assertJson(['error' => true, 'code' => 'FEATURE_DISABLED']);
    }

    public function test_get_webinars_responds_when_the_flag_is_on(): void
    {
        config(['features.eventosWebinars' => true]);

        $this->getJson('/api/webinars')->assertOk();
    }

    /**
     * O formulário de agendamento enviava `description: ''` e data em texto livre
     * ("25 de Junho"). Resultado: TODO agendamento era recusado, e como o cliente
     * inseria na lista local antes de chamar a API e engolia o erro, quem agendava
     * via a confirmação e o webinar nunca chegava ao site.
     */
    public function test_webinar_requires_description(): void
    {
        config(['features.eventosWebinars' => true]);
        $token = $this->tokenForRole('instructor');

        $this->postJson('/api/webinars', [
            'title' => 'Masterclass de Fotografia',
            'date' => '15/09/2026',
            'time' => '19:00',
            'description' => '',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ], $this->auth($token))->assertStatus(400);
    }

    public function test_webinar_can_be_scheduled_and_removed_by_staff(): void
    {
        config(['features.eventosWebinars' => true]);
        $token = $this->tokenForRole('instructor');
        $id = 'web-teste-'.uniqid();

        $this->postJson('/api/webinars', [
            'id' => $id,
            'title' => 'Masterclass de Fotografia',
            'date' => '15/09/2026',
            'time' => '19:00',
            'description' => 'Composição e luz natural para registro cultural.',
            'link' => 'https://meet.google.com/abc-defg-hij',
        ], $this->auth($token))->assertStatus(201)->assertJsonPath('date', '15/09/2026');

        $this->assertDatabaseHas('WebinarEvent', ['id' => $id]);

        // Exclusão existe para a área de gestão poder desmarcar: antes só havia criar
        // e listar, e um webinar agendado por engano ficava na agenda para sempre.
        $this->deleteJson("/api/webinars/{$id}", [], $this->auth($token))->assertOk();
        $this->assertDatabaseMissing('WebinarEvent', ['id' => $id]);
    }

    public function test_student_cannot_remove_a_webinar(): void
    {
        config(['features.eventosWebinars' => true]);

        $this->deleteJson('/api/webinars/web-1', [], $this->auth($this->tokenForRole('student')))
            ->assertStatus(403);
        $this->assertDatabaseHas('WebinarEvent', ['id' => 'web-1']);
    }
}
