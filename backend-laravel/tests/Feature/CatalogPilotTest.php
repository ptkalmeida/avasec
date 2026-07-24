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

    public function test_get_webinars_is_disabled_by_feature_flag(): void
    {
        $response = $this->getJson('/api/webinars');

        $response->assertStatus(404)
            ->assertJson(['error' => true, 'code' => 'FEATURE_DISABLED']);
    }
}
