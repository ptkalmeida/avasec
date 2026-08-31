<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Visibilidade do catálogo (GET /api/courses).
 *
 * A rota é PÚBLICA por escopo declarado (01-visao-geral.md: "catálogo público de
 * cursos") e continua assim. O que era falha é o payload: devolvia o texto de estudo
 * de cada aula, videoUrl, documentos e o link do Meet para qualquer visitante sem
 * login — confirmado ao vivo por curl durante a auditoria de 31/08/2026.
 *
 * Estes testes travam as duas metades: a vitrine sempre visível, e o material só
 * para quem pertence ao curso.
 */
final class CourseCatalogVisibilityTest extends TestCase
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

    /**
     * Um curso da seed que realmente tenha material, para o teste não passar por
     * vacuidade (aula sem conteúdo pareceria "protegida" mesmo sem proteção alguma).
     */
    private function courseIdWithContent(): string
    {
        $id = DB::table('Lesson')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->value('courseId');
        $this->assertNotNull($id, 'Seed sem aula com conteúdo — popular o MySQL de dev.');

        return $id;
    }

    /** @param array<int, mixed> $catalogo @return array<string, mixed> */
    private function curso(array $catalogo, string $courseId): array
    {
        foreach ($catalogo as $curso) {
            if (is_array($curso) && ($curso['id'] ?? null) === $courseId) {
                return $curso;
            }
        }
        $this->fail("Curso {$courseId} ausente do catálogo.");
    }

    /** @param array<string, mixed> $curso */
    private function assertSemMaterial(array $curso): void
    {
        foreach ((array) ($curso['lessons'] ?? []) as $aula) {
            $this->assertSame('', $aula['content'] ?? null, 'Texto de estudo vazou no catálogo.');
            $this->assertNull($aula['videoUrl'] ?? null, 'videoUrl vazou no catálogo.');
            $this->assertSame([], $aula['documents'] ?? null, 'Documentos vazaram no catálogo.');
        }
        foreach ((array) ($curso['liveSessions'] ?? []) as $sessao) {
            $this->assertSame('', $sessao['meetingLink'] ?? null, 'Link da sala ao vivo vazou no catálogo.');
        }
    }

    /** @param array<string, mixed> $curso */
    private function assertComMaterial(array $curso): void
    {
        $conteudos = array_map(
            static fn (mixed $a): string => is_array($a) ? (string) ($a['content'] ?? '') : '',
            (array) ($curso['lessons'] ?? [])
        );
        $this->assertNotEmpty(
            array_filter($conteudos, static fn (string $c): bool => $c !== ''),
            'Quem tem acesso ficou sem o material.'
        );
    }

    public function test_catalog_stays_public(): void
    {
        // O catálogo aberto é escopo declarado; fechar a rota seria remover escopo.
        $this->getJson('/api/courses')->assertOk();
    }

    public function test_anonymous_visitor_gets_showcase_without_study_material(): void
    {
        $courseId = $this->courseIdWithContent();
        $catalogo = $this->getJson('/api/courses')->assertOk()->json();

        $curso = $this->curso($catalogo, $courseId);

        // A vitrine continua completa: é o que sustenta a decisão de se matricular.
        $this->assertNotEmpty($curso['title'] ?? '');
        $this->assertNotEmpty($curso['description'] ?? '');
        $this->assertNotEmpty($curso['lessons'] ?? [], 'O programa do curso deve continuar visível.');
        $this->assertNotEmpty($curso['lessons'][0]['title'] ?? '');

        $this->assertSemMaterial($curso);
    }

    public function test_student_without_enrollment_gets_no_material(): void
    {
        $courseId = $this->courseIdWithContent();
        $aluno = $this->makeStudent();

        $catalogo = $this->getJson('/api/courses', $this->auth($aluno['token']))->assertOk()->json();

        // Estar logado não basta: o acesso é por vínculo com o curso.
        $this->assertSemMaterial($this->curso($catalogo, $courseId));
    }

    public function test_enrolled_student_gets_material_of_own_course_only(): void
    {
        $courseId = $this->courseIdWithContent();
        $outroId = $this->anotherSeededCourseId($courseId);
        $aluno = $this->makeStudent();

        $this->withHeaders($this->auth($aluno['token']))
            ->postJson('/api/enrollments/self/enroll', ['courseId' => $courseId])
            ->assertOk();

        $catalogo = $this->getJson('/api/courses', $this->auth($aluno['token']))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $courseId));
        $this->assertSemMaterial($this->curso($catalogo, $outroId));
    }

    public function test_admin_gets_everything(): void
    {
        $courseId = $this->courseIdWithContent();
        $catalogo = $this->getJson('/api/courses', $this->auth($this->staffToken('admin')))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $courseId));
    }

    public function test_instructor_gets_material_of_courses_they_teach(): void
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $this->assertNotNull($instructorId, 'Seed sem instrutor ativo.');

        $proprio = DB::table('Course')->where('instructorId', $instructorId)
            ->whereIn('id', DB::table('Lesson')->whereNotNull('content')->where('content', '!=', '')->select('courseId'))
            ->value('id');
        if ($proprio === null) {
            $this->markTestSkipped('Seed sem curso com conteúdo atribuído ao instrutor.');
        }

        $catalogo = $this->getJson('/api/courses', $this->auth($this->staffToken('instructor')))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $proprio));
    }

    public function test_invalid_token_is_treated_as_visitor_not_as_error(): void
    {
        // jwt.optional: token quebrado não derruba o catálogo público, só não libera
        // material. Se isso virasse 401, um cookie expirado esconderia a vitrine.
        $this->getJson('/api/courses', ['Authorization' => 'Bearer token-invalido'])
            ->assertOk();
    }
}
