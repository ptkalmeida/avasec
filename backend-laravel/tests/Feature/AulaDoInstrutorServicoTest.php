<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\CourseService;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * As três escritas de UMA aula: criar, atualizar a identificação/vídeo/texto, e
 * editar o texto com trava otimista.
 *
 * O que prendem: que cada uma escreve SÓ a aula tocada (o `PUT /api/courses/{id}`
 * reenviava todas e uma aba velha desfazia o trabalho de outra pessoa), que a
 * posição da aula nova sai do servidor, e que a edição de bloco recusa agir sobre
 * um texto que já mudou.
 */
final class AulaDoInstrutorServicoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private CourseService $cursos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cursos = app(CourseService::class);
    }

    /** @return array{sub:string,name:string,role:string} */
    private function instrutor(string $nome = 'Gestor Aula'): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return ['sub' => $id, 'name' => $nome, 'role' => 'instructor'];
    }

    /** @param array{sub:string,name:string,role:string} $dono */
    private function curso(array $dono, int $aulas = 2): Course
    {
        $curso = Course::query()->create([
            'id' => 'course-aula-'.uniqid(),
            'title' => 'Curso Da Aula Teste',
            'description' => 'Descrição longa o bastante para o validador aceitar.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $dono['sub'],
            'instructorName' => $dono['name'],
        ]);
        Course::query()->whereKey($curso->id)->update(['status' => Visibilidade::PUBLICADO]);

        for ($i = 1; $i <= $aulas; $i++) {
            Lesson::query()->create([
                'id' => 'lesson-'.$curso->id.'-'.$i,
                'courseId' => $curso->id,
                'title' => "Aula {$i}",
                'duration' => '30 min',
                'content' => "Texto {$i}",
                'lesson_order' => $i,
            ]);
        }

        return $curso;
    }

    // ---- criar -------------------------------------------------------------

    public function test_cria_a_aula_no_fim_da_grade_com_a_posicao_calculada_no_servidor(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 2);

        $id = $this->cursos->criarAula($curso->id, ['title' => '  Nova aula  ', 'duration' => '15 min', 'content' => 'Texto novo'], $eu);

        $aula = Lesson::query()->findOrFail($id);
        $this->assertSame('Nova aula', $aula->title);
        $this->assertSame('15 min', $aula->duration);
        $this->assertSame('Texto novo', $aula->content);
        $this->assertSame(3, $aula->order);
        $this->assertNull($aula->videoUrl);
        $this->assertStringStartsWith('lesson-'.$curso->id.'-', $id);
    }

    public function test_a_primeira_aula_de_um_curso_vazio_e_a_numero_um(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 0);

        $id = $this->cursos->criarAula($curso->id, ['title' => 'Primeira', 'duration' => '10 min', 'content' => 'Texto'], $eu);

        $this->assertSame(1, Lesson::query()->findOrFail($id)->order);
    }

    public function test_o_video_e_gravado_na_forma_canonica(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 0);

        $id = $this->cursos->criarAula($curso->id, [
            'title' => 'Com vídeo', 'duration' => '10 min', 'content' => 'Texto', 'videoUrl' => 'https://youtu.be/dQw4w9WgXcQ',
        ], $eu);

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', Lesson::query()->findOrFail($id)->videoUrl);
    }

    public function test_criar_recusa_o_que_o_painel_antigo_recusava(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $base = ['title' => 'Aula', 'duration' => '10 min', 'content' => 'Texto'];

        foreach ([
            'título em branco' => ['title' => '   '],
            'título longo demais' => ['title' => str_repeat('a', 192)],
            'carga em branco' => ['duration' => ''],
            'carga longa demais' => ['duration' => str_repeat('1', 31)],
            'sem texto' => ['content' => '   '],
            'texto longo demais' => ['content' => str_repeat('a', 20001)],
            'vídeo inválido' => ['videoUrl' => 'https://exemplo.com/nada'],
        ] as $caso => $troca) {
            try {
                $this->cursos->criarAula($curso->id, $troca + $base, $eu);
                $this->fail("Aceitou: {$caso}");
            } catch (ApiException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, Lesson::query()->where('courseId', $curso->id)->count());
    }

    public function test_so_o_dono_ou_o_admin_cria_aula(): void
    {
        $dono = $this->instrutor('Dono');
        $outro = $this->instrutor('Outro');
        $curso = $this->curso($dono, 1);

        $this->expectException(ApiException::class);

        try {
            $this->cursos->criarAula($curso->id, ['title' => 'Invasora', 'duration' => '10 min', 'content' => 'Texto'], $outro);
        } finally {
            $this->assertSame(1, Lesson::query()->where('courseId', $curso->id)->count());
        }
    }

    // ---- atualizar ---------------------------------------------------------

    public function test_atualizar_muda_so_o_que_foi_enviado_e_so_naquela_aula(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 2);
        $primeira = 'lesson-'.$curso->id.'-1';
        $segunda = 'lesson-'.$curso->id.'-2';

        $this->cursos->atualizarAula($curso->id, $primeira, ['title' => 'Título novo'], $eu);

        $a = Lesson::query()->findOrFail($primeira);
        $this->assertSame('Título novo', $a->title);
        $this->assertSame('30 min', $a->duration);
        $this->assertSame('Texto 1', $a->content);

        $b = Lesson::query()->findOrFail($segunda);
        $this->assertSame('Aula 2', $b->title);
        $this->assertSame('Texto 2', $b->content);
        $this->assertSame(2, $b->order);
    }

    public function test_atualizar_a_identificacao_nao_toca_na_ordem(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 3);
        $meio = 'lesson-'.$curso->id.'-2';

        $this->cursos->atualizarAula($curso->id, $meio, ['title' => 'Meio', 'duration' => '99 min'], $eu);

        $this->assertSame(2, Lesson::query()->findOrFail($meio)->order);
    }

    public function test_video_vazio_remove_o_video(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        $this->cursos->atualizarAula($curso->id, $id, ['videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], $eu);
        $this->assertNotNull(Lesson::query()->findOrFail($id)->videoUrl);

        $this->cursos->atualizarAula($curso->id, $id, ['videoUrl' => ''], $eu);
        $this->assertNull(Lesson::query()->findOrFail($id)->videoUrl);
    }

    public function test_atualizar_recusa_titulo_vazio_e_video_invalido(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        foreach ([['title' => ' '], ['videoUrl' => 'isto não é um link'], ['duration' => '']] as $campos) {
            try {
                $this->cursos->atualizarAula($curso->id, $id, $campos, $eu);
                $this->fail('Aceitou '.json_encode($campos));
            } catch (ApiException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Aula 1', Lesson::query()->findOrFail($id)->title);
    }

    public function test_aula_de_outro_curso_ou_ja_retirada_nao_e_alcancada(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $outroCurso = $this->curso($eu, 1);

        try {
            $this->cursos->atualizarAula($curso->id, 'lesson-'.$outroCurso->id.'-1', ['title' => 'Cruzada'], $eu);
            $this->fail('Alcançou a aula de outro curso');
        } catch (ApiException) {
            $this->addToAssertionCount(1);
        }

        $this->cursos->inativarAula($curso->id, 'lesson-'.$curso->id.'-1', $eu);
        $this->expectException(ApiException::class);
        $this->cursos->atualizarAula($curso->id, 'lesson-'.$curso->id.'-1', ['title' => 'Zumbi'], $eu);
    }

    public function test_instrutor_de_outro_curso_nao_atualiza(): void
    {
        $dono = $this->instrutor('Dono');
        $outro = $this->instrutor('Outro');
        $curso = $this->curso($dono, 1);

        try {
            $this->cursos->atualizarAula($curso->id, 'lesson-'.$curso->id.'-1', ['title' => 'Invasor'], $outro);
            $this->fail('Outro instrutor atualizou');
        } catch (ApiException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('Aula 1', Lesson::query()->findOrFail('lesson-'.$curso->id.'-1')->title);
    }

    // ---- editar o texto com trava otimista ----------------------------------

    public function test_edita_o_texto_quando_a_versao_confere(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        $novo = $this->cursos->editarConteudoDaAula(
            $curso->id,
            $id,
            CourseService::versaoDoConteudo('Texto 1'),
            static fn (string $atual): string => $atual."\n\n## Seção nova",
            $eu,
        );

        $this->assertSame("Texto 1\n\n## Seção nova", $novo);
        $this->assertSame($novo, Lesson::query()->findOrFail($id)->content);
    }

    public function test_recusa_a_edicao_sobre_um_texto_que_ja_mudou(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';
        $versaoVelha = CourseService::versaoDoConteudo('Texto 1');

        // Outra pessoa salvou no meio.
        $this->cursos->atualizarAula($curso->id, $id, ['content' => 'Texto da outra pessoa'], $eu);

        try {
            $this->cursos->editarConteudoDaAula($curso->id, $id, $versaoVelha, static fn (string $a): string => 'Sobrescrito', $eu);
            $this->fail('Aplicou a edição sobre texto novo');
        } catch (ApiException $e) {
            $this->assertStringContainsString('mudou', $e->getMessage());
        }

        $this->assertSame('Texto da outra pessoa', Lesson::query()->findOrFail($id)->content);
    }

    public function test_a_edicao_que_esvaziaria_a_aula_e_recusada(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        $this->expectException(ApiException::class);

        try {
            $this->cursos->editarConteudoDaAula($curso->id, $id, CourseService::versaoDoConteudo('Texto 1'), static fn (string $a): string => '', $eu);
        } finally {
            $this->assertSame('Texto 1', Lesson::query()->findOrFail($id)->content);
        }
    }

    public function test_aula_sem_texto_tem_versao_estavel(): void
    {
        $this->assertSame(CourseService::versaoDoConteudo(null), CourseService::versaoDoConteudo(''));
        $this->assertNotSame(CourseService::versaoDoConteudo('a'), CourseService::versaoDoConteudo('b'));
    }
}
