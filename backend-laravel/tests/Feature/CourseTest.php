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
        $u = DB::table('usuario')->where('papel', $role)->where('status', 'active')->first(['id', 'nome', 'papel']);
        $this->assertNotNull($u, "Seed sem usuário ativo com role '$role'.");

        return Jwt::issue($u->id, $u->nome, $u->papel);
    }

    public function test_get_courses_is_public_and_returns_nested_lessons(): void
    {
        $response = $this->getJson('/api/cursos');
        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        // Contrato aninhado: cada curso traz arrays lessons e liveSessions.
        $this->assertArrayHasKey('aulas', $data[0]);
        $this->assertArrayHasKey('sessoes_ao_vivo', $data[0]);
    }

    public function test_creating_course_without_token_is_401(): void
    {
        $this->postJson('/api/cursos', ['titulo' => 'x'])->assertStatus(401);
    }

    public function test_admin_can_create_course_with_lesson_and_it_persists_order(): void
    {
        $token = $this->tokenForRole('admin');
        $id = 'course-test-'.uniqid();

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/cursos', [
            'id' => $id,
            'titulo' => 'Curso de Teste Automatizado',
            'descricao' => 'Descrição suficientemente longa para passar.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
            'nome_instrutor' => 'Gestor de Conteúdos',
            'aulas' => [
                ['titulo' => 'Aula 1', 'duracao' => '10min', 'ordem' => 0],
                ['titulo' => 'Aula 2', 'duracao' => '12min', 'ordem' => 1],
            ],
        ]);

        $response->assertStatus(201)->assertJsonPath('id', $id);
        $lessons = $response->json('aulas');
        $this->assertCount(2, $lessons);
        $this->assertSame(0, $lessons[0]['ordem']);
        $this->assertSame(1, $lessons[1]['ordem']);
        $this->assertDatabaseHas('curso', ['id' => $id]);
    }

    public function test_short_title_and_description_are_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/cursos', [
            'titulo' => 'ab',
            'descricao' => 'curta',
            'categoria' => 'X',
            'miniatura' => 'y',
            'nome_instrutor' => 'Z',
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_lesson_with_invalid_video_url_is_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/cursos', [
            'titulo' => 'Curso com vídeo inválido',
            'descricao' => 'Descrição suficientemente longa para passar.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
            'nome_instrutor' => 'Gestor de Conteúdos',
            'aulas' => [
                ['titulo' => 'Aula 1', 'duracao' => '10min', 'ordem' => 0, 'url_video' => 'https://example.com/pagina-qualquer'],
            ],
        ])->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_lesson_video_url_is_canonicalized_on_create(): void
    {
        $token = $this->tokenForRole('admin');
        $id = 'course-video-'.uniqid();

        $response = $this->withHeader('Authorization', "Bearer $token")->postJson('/api/cursos', [
            'id' => $id,
            'titulo' => 'Curso com vídeo do YouTube',
            'descricao' => 'Descrição suficientemente longa para passar.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
            'nome_instrutor' => 'Gestor de Conteúdos',
            'aulas' => [
                ['titulo' => 'Aula 1', 'duracao' => '10min', 'ordem' => 0, 'url_video' => 'https://youtu.be/dQw4w9WgXcQ?si=abc123'],
                ['titulo' => 'Aula 2', 'duracao' => '12min', 'ordem' => 1, 'url_video' => null],
            ],
        ]);

        $response->assertStatus(201);
        $lessons = $response->json('aulas');
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $lessons[0]['url_video']);
        $this->assertNull($lessons[1]['url_video']);
    }

    public function test_instructor_cannot_edit_course_not_owned(): void
    {
        // Curso criado pelo admin fica atribuído ao próprio admin (FK); um
        // instrutor diferente não pode editar nem excluir.
        $adminToken = $this->tokenForRole('admin');
        $id = 'course-owned-'.uniqid();
        $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/cursos', [
            'id' => $id,
            'titulo' => 'Curso do Admin',
            'descricao' => 'Descrição longa o suficiente aqui.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
        ])->assertStatus(201);

        $instructorToken = $this->tokenForRole('instructor');
        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->putJson("/api/cursos/{$id}", ['titulo' => 'Tentativa de sequestro'])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->deleteJson("/api/cursos/{$id}")
            ->assertStatus(403);
    }

    public function test_admin_assigns_course_to_instructor_by_user_id(): void
    {
        $adminToken = $this->tokenForRole('admin');
        $instructor = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotNull($instructor);

        $id = 'course-assign-'.uniqid();
        $response = $this->withHeader('Authorization', "Bearer $adminToken")->postJson('/api/cursos', [
            'id' => $id,
            'titulo' => 'Curso Atribuído por FK',
            'descricao' => 'Descrição longa o suficiente aqui.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
            'instrutor_id' => $instructor->id,
            // nome_instrutor divergente de propósito: deve ser IGNORADO (display deriva do Usuario).
            'nome_instrutor' => 'Nome Que Nao Vale',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('instrutor_id', $instructor->id)
            ->assertJsonPath('nome_instrutor', $instructor->nome);
    }

    public function test_instructor_cannot_reassign_course_authorship(): void
    {
        $instructorToken = $this->tokenForRole('instructor');
        $id = 'course-mine-'.uniqid();
        $this->withHeader('Authorization', "Bearer $instructorToken")->postJson('/api/cursos', [
            'id' => $id,
            'titulo' => 'Curso do Instrutor',
            'descricao' => 'Descrição longa o suficiente aqui.',
            'categoria' => 'Testes',
            'miniatura' => 'https://example.com/t.png',
        ])->assertStatus(201);

        $admin = DB::table('usuario')->where('papel', 'admin')->first(['id']);
        $this->withHeader('Authorization', "Bearer $instructorToken")
            ->putJson("/api/cursos/{$id}", ['instrutor_id' => $admin->id])
            ->assertStatus(403);
    }
}
