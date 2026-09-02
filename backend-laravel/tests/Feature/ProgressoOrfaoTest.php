<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\StudentProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Resíduo de aula apagada no progresso do aluno.
 *
 * `StudentProgress.completedLessons` guarda ids de aula. A escrita já filtrava
 * id inválido, mas nada limpava o que estava gravado quando a aula era APAGADA
 * depois — e o resíduo era contado como aula concluída. Efeito medido em dados
 * reais: um curso com 1 aula e 2 ids órfãos dava 200% de progresso, e o Perfil
 * exibia "113% de progresso médio". A mesma conta inflada é a que decide a
 * emissão automática de certificado.
 */
final class ProgressoOrfaoTest extends TestCase
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

    /** Curso do instrutor do seed com pelo menos duas aulas. */
    private function cursoComAulas(): Course
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $curso = Course::query()->with('lessons')->where('instructorId', $instructorId)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');
        $this->assertGreaterThan(1, $curso->lessons->count(), 'Curso do seed precisa de 2+ aulas.');

        return $curso;
    }

    /** Payload de PUT /courses/{id} preservando as aulas informadas. */
    private function payloadComAulas(Course $curso, array $aulas): array
    {
        return [
            'title' => $curso->title,
            'description' => $curso->description,
            'category' => $curso->category,
            'lessons' => $aulas,
        ];
    }

    public function test_apagar_aula_limpa_o_progresso_dos_alunos(): void
    {
        $curso = $this->cursoComAulas();
        $aluno = $this->makeStudent('Aluno Progresso');
        $aulas = $curso->lessons->pluck('id')->all();

        StudentProgress::query()->create([
            'id' => 'prog-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $curso->id,
            'completedLessons' => $aulas,
            'attendedLiveSessions' => [],
        ]);

        // O gestor remove a primeira aula do curso (PUT reenvia as que ficam).
        $ficam = $curso->lessons->slice(1)->map(fn ($l) => [
            'id' => $l->id,
            'title' => $l->title,
            'content' => $l->content ?? 'x',
            'duration' => $l->duration ?? '10 min',
            'order' => $l->order ?? 1,
        ])->values()->all();

        $this->putJson(
            "/api/courses/{$curso->id}",
            $this->payloadComAulas($curso, $ficam),
            $this->auth($this->staffToken('instructor'))
        )->assertOk();

        $registro = StudentProgress::query()
            ->where('userId', $aluno['id'])->where('courseId', $curso->id)->firstOrFail();

        // O id da aula apagada não pode continuar contando como aula concluída.
        $this->assertNotContains($aulas[0], $registro->completedLessons);
        $this->assertCount(count($ficam), $registro->completedLessons);
    }

    public function test_registro_de_outro_curso_nao_e_tocado(): void
    {
        $curso = $this->cursoComAulas();
        $outroId = DB::table('Course')->where('id', '!=', $curso->id)->value('id');
        $this->assertNotNull($outroId, 'Seed sem um segundo curso.');

        $aluno = $this->makeStudent('Aluno Outro Curso');
        StudentProgress::query()->create([
            'id' => 'prog-'.uniqid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'courseId' => $outroId,
            'completedLessons' => ['id-que-nao-existe'],
            'attendedLiveSessions' => [],
        ]);

        $ficam = $curso->lessons->slice(1)->map(fn ($l) => [
            'id' => $l->id, 'title' => $l->title, 'content' => $l->content ?? 'x',
            'duration' => $l->duration ?? '10 min', 'order' => $l->order ?? 1,
        ])->values()->all();

        $this->putJson(
            "/api/courses/{$curso->id}",
            $this->payloadComAulas($curso, $ficam),
            $this->auth($this->staffToken('instructor'))
        )->assertOk();

        // A limpeza é por curso: mexer no curso A não pode reescrever o progresso
        // de B (que tem a própria higiene quando B for editado).
        $registro = StudentProgress::query()
            ->where('userId', $aluno['id'])->where('courseId', $outroId)->firstOrFail();
        $this->assertSame(['id-que-nao-existe'], $registro->completedLessons);
    }

    public function test_progresso_gravado_pela_api_ja_descarta_id_invalido(): void
    {
        $curso = $this->cursoComAulas();
        $aluno = $this->makeStudent('Aluno Envio');
        $valida = $curso->lessons->first()->id;

        $this->postJson('/api/progress', [
            'courseId' => $curso->id,
            'completedLessons' => [$valida, 'aula-inventada'],
            'attendedLiveSessions' => ['sessao-inventada'],
        ], $this->auth($aluno['token']))->assertSuccessful();

        $registro = StudentProgress::query()
            ->where('userId', $aluno['id'])->where('courseId', $curso->id)->firstOrFail();

        $this->assertSame([$valida], $registro->completedLessons);
        $this->assertSame([], $registro->attendedLiveSessions);
    }
}
