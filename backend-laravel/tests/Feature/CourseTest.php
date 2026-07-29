<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Etapa 3 — módulo de Cursos. Espelha os casos de courseRoutes/courseService do Node.
 * Requer MySQL de dev populado. Escritas em transação revertida.
 */
final class CourseTest extends TestCase
{
    use DatabaseTransactions;

    private function tokenForRole(string $role): string
    {
        $u = DB::table('User')->where('role', $role)->where('status', 'active')->first(['id', 'name', 'role']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($u->id, $u->name, $u->role);
    }

    public function test_get_courses_is_public_and_returns_nested_lessons(): void
    {
        $response = $this->getJson('/api/courses');
        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        // Contrato aninhado: cada curso traz arrays lessons e liveSessions.
        $this->assertArrayHasKey('lessons', $data[0]);
        $this->assertArrayHasKey('liveSessions', $data[0]);
    }

    public function test_creating_course_without_token_is_401(): void
    {
        $this->postJson('/api/courses', ['title' => 'x'])->assertStatus(401);
    }

    public function test_admin_can_create_course_with_lesson_and_it_persists_order(): void
    {
        $token = $this->tokenForRole('admin');
        $id = 'course-test-'.uniqid();

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/courses', [
            'id' => $id,
            'title' => 'Curso de Teste Automatizado',
            'description' => 'Descrição suficientemente longa para passar.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
            'instructorName' => 'Gestor de Conteúdos',
            'lessons' => [
                ['title' => 'Aula 1', 'duration' => '10min', 'order' => 0],
                ['title' => 'Aula 2', 'duration' => '12min', 'order' => 1],
            ],
        ]);

        $response->assertStatus(201)->assertJsonPath('id', $id);
        $lessons = $response->json('lessons');
        $this->assertCount(2, $lessons);
        $this->assertSame(0, $lessons[0]['order']);
        $this->assertSame(1, $lessons[1]['order']);
        $this->assertDatabaseHas('Course', ['id' => $id]);
    }

    public function test_short_title_and_description_are_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/courses', [
            'title' => 'ab',
            'description' => 'curta',
            'category' => 'X',
            'thumbnail' => 'y',
            'instructorName' => 'Z',
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_lesson_with_invalid_video_url_is_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/courses', [
            'title' => 'Curso com vídeo inválido',
            'description' => 'Descrição suficientemente longa para passar.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
            'instructorName' => 'Gestor de Conteúdos',
            'lessons' => [
                ['title' => 'Aula 1', 'duration' => '10min', 'order' => 0, 'videoUrl' => 'https://example.com/pagina-qualquer'],
            ],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_lesson_video_url_is_canonicalized_on_create(): void
    {
        $token = $this->tokenForRole('admin');
        $id = 'course-video-'.uniqid();

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/courses', [
            'id' => $id,
            'title' => 'Curso com vídeo do YouTube',
            'description' => 'Descrição suficientemente longa para passar.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
            'instructorName' => 'Gestor de Conteúdos',
            'lessons' => [
                ['title' => 'Aula 1', 'duration' => '10min', 'order' => 0, 'videoUrl' => 'https://youtu.be/dQw4w9WgXcQ?si=abc123'],
                ['title' => 'Aula 2', 'duration' => '12min', 'order' => 1, 'videoUrl' => null],
            ],
        ]);

        $response->assertStatus(201);
        $lessons = $response->json('lessons');
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $lessons[0]['videoUrl']);
        $this->assertNull($lessons[1]['videoUrl']);
    }

    public function test_instructor_cannot_edit_course_not_owned(): void
    {
        // Curso criado pelo admin fica atribuído ao próprio admin (FK); um
        // instrutor diferente não pode editar nem excluir.
        $adminToken = $this->tokenForRole('admin');
        $id = 'course-owned-'.uniqid();
        $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/courses', [
            'id' => $id,
            'title' => 'Curso do Admin',
            'description' => 'Descrição longa o suficiente aqui.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
        ])->assertStatus(201);

        $instructorToken = $this->tokenForRole('instructor');
        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->putJson("/api/courses/{$id}", ['title' => 'Tentativa de sequestro'])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->deleteJson("/api/courses/{$id}")
            ->assertStatus(403);
    }

    public function test_admin_assigns_course_to_instructor_by_user_id(): void
    {
        $adminToken = $this->tokenForRole('admin');
        $instructor = DB::table('User')->where('role', 'instructor')->where('status', 'active')->first(['id', 'name']);
        $this->assertNotNull($instructor);

        $id = 'course-assign-'.uniqid();
        $response = $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/courses', [
            'id' => $id,
            'title' => 'Curso Atribuído por FK',
            'description' => 'Descrição longa o suficiente aqui.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
            'instructorId' => $instructor->id,
            // instructorName divergente de propósito: deve ser IGNORADO (display deriva do User).
            'instructorName' => 'Nome Que Nao Vale',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('instructorId', $instructor->id)
            ->assertJsonPath('instructorName', $instructor->name);
    }

    public function test_instructor_cannot_reassign_course_authorship(): void
    {
        $instructorToken = $this->tokenForRole('instructor');
        $id = 'course-mine-'.uniqid();
        $this->withHeader('Authorization', "Bearer $instructorToken")->postJson('/api/courses', [
            'id' => $id,
            'title' => 'Curso do Instrutor',
            'description' => 'Descrição longa o suficiente aqui.',
            'category' => 'Testes',
            'thumbnail' => 'https://example.com/t.png',
        ])->assertStatus(201);

        $admin = DB::table('User')->where('role', 'admin')->first(['id']);
        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->putJson("/api/courses/{$id}", ['instructorId' => $admin->id])
            ->assertStatus(403);
    }
}
