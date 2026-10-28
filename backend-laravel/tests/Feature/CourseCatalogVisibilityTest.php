<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Fuso;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Visibilidade do catálogo (GET /api/cursos).
 *
 * A rota é PÚBLICA por escopo declarado (01-visao-geral.md: "catálogo público de
 * cursos") e continua assim. O que era falha é o payload: devolvia o texto de estudo
 * de cada aula, url_video, documentos e o link do Meet para qualquer visitante sem
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
        $this->ativarCursosDeSeed();
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
        $id = DB::table('aula')
            ->whereNotNull('conteudo')
            ->where('conteudo', '!=', '')
            ->value('curso_id');
        $this->assertNotNull($id, 'Seed sem aula com conteúdo — popular o MySQL de dev.');

        return $id;
    }

    /** @param array<int, mixed> $catalogo @return array<string, mixed> */
    private function curso(array $catalogo, string $curso_id): array
    {
        foreach ($catalogo as $curso) {
            if (is_array($curso) && ($curso['id'] ?? null) === $curso_id) {
                return $curso;
            }
        }
        $this->fail("Curso {$curso_id} ausente do catálogo.");
    }

    /** @param array<string, mixed> $curso */
    private function assertSemMaterial(array $curso): void
    {
        foreach ((array) ($curso['aulas'] ?? []) as $aula) {
            $this->assertSame('', $aula['conteudo'] ?? null, 'Texto de estudo vazou no catálogo.');
            $this->assertNull($aula['url_video'] ?? null, 'url_video vazou no catálogo.');
            $this->assertSame([], $aula['documentos'] ?? null, 'Documentos vazaram no catálogo.');
        }
        foreach ((array) ($curso['sessoes_ao_vivo'] ?? []) as $sessao) {
            $this->assertSame('', $sessao['link_reuniao'] ?? null, 'Link da sala ao vivo vazou no catálogo.');
        }
    }

    /** @param array<string, mixed> $curso */
    private function assertComMaterial(array $curso): void
    {
        $conteudos = array_map(
            static fn (mixed $a): string => is_array($a) ? (string) ($a['conteudo'] ?? '') : '',
            (array) ($curso['aulas'] ?? [])
        );
        $this->assertNotEmpty(
            array_filter($conteudos, static fn (string $c): bool => $c !== ''),
            'Quem tem acesso ficou sem o material.'
        );
    }

    public function test_catalog_stays_public(): void
    {
        // O catálogo aberto é escopo declarado; fechar a rota seria remover escopo.
        $this->getJson('/api/cursos')->assertOk();
    }

    public function test_anonymous_visitor_gets_showcase_without_study_material(): void
    {
        $curso_id = $this->courseIdWithContent();
        $catalogo = $this->getJson('/api/cursos')->assertOk()->json();

        $curso = $this->curso($catalogo, $curso_id);

        // A vitrine continua completa: é o que sustenta a decisão de se matricular.
        $this->assertNotEmpty($curso['titulo'] ?? '');
        $this->assertNotEmpty($curso['descricao'] ?? '');
        $this->assertNotEmpty($curso['aulas'] ?? [], 'O programa do curso deve continuar visível.');
        $this->assertNotEmpty($curso['aulas'][0]['titulo'] ?? '');

        $this->assertSemMaterial($curso);
    }

    public function test_student_without_enrollment_gets_no_material(): void
    {
        $curso_id = $this->courseIdWithContent();
        $aluno = $this->makeStudent();

        $catalogo = $this->getJson('/api/cursos', $this->auth($aluno['token']))->assertOk()->json();

        // Estar logado não basta: o acesso é por vínculo com o curso.
        $this->assertSemMaterial($this->curso($catalogo, $curso_id));
    }

    public function test_enrolled_student_gets_material_of_own_course_only(): void
    {
        $curso_id = $this->courseIdWithContent();
        $outroId = $this->anotherSeededCourseId($curso_id);
        $aluno = $this->makeStudent();

        $this->withHeaders($this->auth($aluno['token']))
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $curso_id])
            ->assertOk();

        $catalogo = $this->getJson('/api/cursos', $this->auth($aluno['token']))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $curso_id));
        $this->assertSemMaterial($this->curso($catalogo, $outroId));
    }

    /**
     * Matrícula múltipla: o segundo curso vai para `cursos_extras_ids`, e o painel do
     * aluno o abre como matrícula. CourseAccess não lia esse campo, então o curso
     * extra chegava pela vitrine — aula em branco e nenhum anexo, com o documento
     * íntegro no banco. Relatado em 23/09/2026 no curso de Portfólio.
     */
    public function test_student_gets_material_and_documents_of_extra_course(): void
    {
        config(['features.matriculasMultiplas' => true]);

        // Curso extra: precisa ter aula com conteúdo E documento ativo, senão o
        // teste passaria por vacuidade na metade que motivou a correção.
        // No prazo: curso vencido não aceita matrícula nova desde 21/10, e o
        // banco de desenvolvimento tem um vencido. Este teste mede o MATERIAL
        // do curso extra, não a vigência.
        $hoje = Fuso::agora()->format('Y-m-d');
        $noPrazo = static fn ($q) => $q->whereNull('vigencia_ate')->orWhere('vigencia_ate', '>=', $hoje);

        $extraId = DB::table('curso')
            ->whereNull('inativado_em')->where('status', 1)
            ->where($noPrazo)
            ->whereIn('id', DB::table('aula')
                ->whereNull('inativado_em')
                ->whereNotNull('conteudo')->where('conteudo', '!=', '')
                ->whereIn('id', DB::table('documento_aula')->whereNull('inativado_em')->where('status', 1)->select('aula_id'))
                ->select('curso_id'))
            ->value('id');
        $this->assertNotNull($extraId, 'Seed sem curso com aula de conteúdo e documento — popular o MySQL de dev.');

        $principalId = DB::table('curso')
            ->whereNull('inativado_em')->where('status', 1)
            ->where($noPrazo)
            ->where('id', '!=', $extraId)
            ->value('id');
        $this->assertNotNull($principalId, 'Seed sem um segundo curso publicado.');

        $aluno = $this->makeStudent();
        $auth = $this->auth($aluno['token']);

        $this->withHeaders($this->auth($this->staffToken('admin')))
            ->putJson('/api/matriculas/'.$aluno['id'], ['pode_multimatricula' => true])
            ->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $principalId])
            ->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/matriculas/proprio/matricular', ['curso_id' => $extraId])
            ->assertOk()
            ->assertJsonPath('enrollment.cursos_extras_ids', [$extraId]);

        $curso = $this->curso(
            $this->getJson('/api/cursos', $auth)->assertOk()->json(),
            $extraId
        );

        $this->assertComMaterial($curso);
        $documentos = array_merge(...array_map(
            static fn (mixed $a): array => is_array($a) ? (array) ($a['documentos'] ?? []) : [],
            (array) ($curso['aulas'] ?? [])
        ));
        $this->assertNotEmpty($documentos, 'Aluno do curso extra ficou sem os documentos anexados.');
    }

    public function test_admin_gets_everything(): void
    {
        $curso_id = $this->courseIdWithContent();
        $catalogo = $this->getJson('/api/cursos', $this->auth($this->staffToken('admin')))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $curso_id));
    }

    public function test_instructor_gets_material_of_courses_they_teach(): void
    {
        $instrutor_id = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->value('id');
        $this->assertNotNull($instrutor_id, 'Seed sem instrutor ativo.');

        $proprio = DB::table('curso')->where('instrutor_id', $instrutor_id)
            ->whereIn('id', DB::table('aula')->whereNotNull('conteudo')->where('conteudo', '!=', '')->select('curso_id'))
            ->value('id');
        if ($proprio === null) {
            $this->markTestSkipped('Seed sem curso com conteúdo atribuído ao instrutor.');
        }

        $catalogo = $this->getJson('/api/cursos', $this->auth($this->staffToken('instructor')))->assertOk()->json();

        $this->assertComMaterial($this->curso($catalogo, $proprio));
    }

    public function test_invalid_token_is_treated_as_visitor_not_as_error(): void
    {
        // jwt.optional: token quebrado não derruba o catálogo público, só não libera
        // material. Se isso virasse 401, um cookie expirado esconderia a vitrine.
        $this->getJson('/api/cursos', ['Authorization' => 'Bearer token-invalido'])
            ->assertOk();
    }
}
