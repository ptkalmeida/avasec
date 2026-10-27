<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Usuario;
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
 * O que prendem: que cada uma escreve SÓ a aula tocada (o `PUT /api/cursos/{id}`
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
        Usuario::query()->create([
            'id' => $id,
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
        ]);

        return ['sub' => $id, 'nome' => $nome, 'papel' => 'instructor'];
    }

    /** @param array{sub:string,name:string,role:string} $dono */
    private function curso(array $dono, int $aulas = 2): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-aula-'.uniqid(),
            'titulo' => 'Curso Da Aula Teste',
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $dono['sub'],
            'nome_instrutor' => $dono['nome'],
        ]);
        Curso::query()->whereKey($curso->id)->update(['status' => Visibilidade::PUBLICADO]);

        for ($i = 1; $i <= $aulas; $i++) {
            Aula::query()->create([
                'id' => 'lesson-'.$curso->id.'-'.$i,
                'curso_id' => $curso->id,
                'titulo' => "Aula {$i}",
                'duracao' => '30 min',
                'conteudo' => "Texto {$i}",
                'ordem' => $i,
            ]);
        }

        return $curso;
    }

    // ---- criar -------------------------------------------------------------

    public function test_cria_a_aula_no_fim_da_grade_com_a_posicao_calculada_no_servidor(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 2);

        $id = $this->cursos->criarAula($curso->id, ['titulo' => '  Nova aula  ', 'duracao' => '15 min', 'conteudo' => 'Texto novo'], $eu);

        $aula = Aula::query()->findOrFail($id);
        $this->assertSame('Nova aula', $aula->titulo);
        $this->assertSame('15 min', $aula->duracao);
        $this->assertSame('Texto novo', $aula->conteudo);
        $this->assertSame(3, $aula->ordem);
        $this->assertNull($aula->url_video);
        $this->assertStringStartsWith('lesson-'.$curso->id.'-', $id);
    }

    public function test_a_primeira_aula_de_um_curso_vazio_e_a_numero_um(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 0);

        $id = $this->cursos->criarAula($curso->id, ['titulo' => 'Primeira', 'duracao' => '10 min', 'conteudo' => 'Texto'], $eu);

        $this->assertSame(1, Aula::query()->findOrFail($id)->ordem);
    }

    public function test_o_video_e_gravado_na_forma_canonica(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 0);

        $id = $this->cursos->criarAula($curso->id, [
            'titulo' => 'Com vídeo', 'duracao' => '10 min', 'conteudo' => 'Texto', 'url_video' => 'https://youtu.be/dQw4w9WgXcQ',
        ], $eu);

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', Aula::query()->findOrFail($id)->url_video);
    }

    public function test_criar_recusa_o_que_o_painel_antigo_recusava(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $base = ['titulo' => 'Aula', 'duracao' => '10 min', 'conteudo' => 'Texto'];

        foreach ([
            'título em branco' => ['titulo' => '   '],
            'título longo demais' => ['titulo' => str_repeat('a', 192)],
            'carga em branco' => ['duracao' => ''],
            'carga longa demais' => ['duracao' => str_repeat('1', 31)],
            'sem texto' => ['conteudo' => '   '],
            'texto longo demais' => ['conteudo' => str_repeat('a', 20001)],
            'vídeo inválido' => ['url_video' => 'https://exemplo.com/nada'],
        ] as $caso => $troca) {
            try {
                $this->cursos->criarAula($curso->id, $troca + $base, $eu);
                $this->fail("Aceitou: {$caso}");
            } catch (ApiException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, Aula::query()->where('curso_id', $curso->id)->count());
    }

    public function test_so_o_dono_ou_o_admin_cria_aula(): void
    {
        $dono = $this->instrutor('Dono');
        $outro = $this->instrutor('Outro');
        $curso = $this->curso($dono, 1);

        $this->expectException(ApiException::class);

        try {
            $this->cursos->criarAula($curso->id, ['titulo' => 'Invasora', 'duracao' => '10 min', 'conteudo' => 'Texto'], $outro);
        } finally {
            $this->assertSame(1, Aula::query()->where('curso_id', $curso->id)->count());
        }
    }

    // ---- atualizar ---------------------------------------------------------

    public function test_atualizar_muda_so_o_que_foi_enviado_e_so_naquela_aula(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 2);
        $primeira = 'lesson-'.$curso->id.'-1';
        $segunda = 'lesson-'.$curso->id.'-2';

        $this->cursos->atualizarAula($curso->id, $primeira, ['titulo' => 'Título novo'], $eu);

        $a = Aula::query()->findOrFail($primeira);
        $this->assertSame('Título novo', $a->titulo);
        $this->assertSame('30 min', $a->duracao);
        $this->assertSame('Texto 1', $a->conteudo);

        $b = Aula::query()->findOrFail($segunda);
        $this->assertSame('Aula 2', $b->titulo);
        $this->assertSame('Texto 2', $b->conteudo);
        $this->assertSame(2, $b->ordem);
    }

    public function test_atualizar_a_identificacao_nao_toca_na_ordem(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 3);
        $meio = 'lesson-'.$curso->id.'-2';

        $this->cursos->atualizarAula($curso->id, $meio, ['titulo' => 'Meio', 'duracao' => '99 min'], $eu);

        $this->assertSame(2, Aula::query()->findOrFail($meio)->ordem);
    }

    public function test_video_vazio_remove_o_video(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        $this->cursos->atualizarAula($curso->id, $id, ['url_video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], $eu);
        $this->assertNotNull(Aula::query()->findOrFail($id)->url_video);

        $this->cursos->atualizarAula($curso->id, $id, ['url_video' => ''], $eu);
        $this->assertNull(Aula::query()->findOrFail($id)->url_video);
    }

    public function test_atualizar_recusa_titulo_vazio_e_video_invalido(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';

        foreach ([['titulo' => ' '], ['url_video' => 'isto não é um link'], ['duracao' => '']] as $campos) {
            try {
                $this->cursos->atualizarAula($curso->id, $id, $campos, $eu);
                $this->fail('Aceitou '.json_encode($campos));
            } catch (ApiException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Aula 1', Aula::query()->findOrFail($id)->titulo);
    }

    public function test_aula_de_outro_curso_ou_ja_retirada_nao_e_alcancada(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $outroCurso = $this->curso($eu, 1);

        try {
            $this->cursos->atualizarAula($curso->id, 'lesson-'.$outroCurso->id.'-1', ['titulo' => 'Cruzada'], $eu);
            $this->fail('Alcançou a aula de outro curso');
        } catch (ApiException) {
            $this->addToAssertionCount(1);
        }

        $this->cursos->inativarAula($curso->id, 'lesson-'.$curso->id.'-1', $eu);
        $this->expectException(ApiException::class);
        $this->cursos->atualizarAula($curso->id, 'lesson-'.$curso->id.'-1', ['titulo' => 'Zumbi'], $eu);
    }

    public function test_instrutor_de_outro_curso_nao_atualiza(): void
    {
        $dono = $this->instrutor('Dono');
        $outro = $this->instrutor('Outro');
        $curso = $this->curso($dono, 1);

        try {
            $this->cursos->atualizarAula($curso->id, 'lesson-'.$curso->id.'-1', ['titulo' => 'Invasor'], $outro);
            $this->fail('Outro instrutor atualizou');
        } catch (ApiException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('Aula 1', Aula::query()->findOrFail('lesson-'.$curso->id.'-1')->titulo);
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
        $this->assertSame($novo, Aula::query()->findOrFail($id)->conteudo);
    }

    public function test_recusa_a_edicao_sobre_um_texto_que_ja_mudou(): void
    {
        $eu = $this->instrutor();
        $curso = $this->curso($eu, 1);
        $id = 'lesson-'.$curso->id.'-1';
        $versaoVelha = CourseService::versaoDoConteudo('Texto 1');

        // Outra pessoa salvou no meio.
        $this->cursos->atualizarAula($curso->id, $id, ['conteudo' => 'Texto da outra pessoa'], $eu);

        try {
            $this->cursos->editarConteudoDaAula($curso->id, $id, $versaoVelha, static fn (string $a): string => 'Sobrescrito', $eu);
            $this->fail('Aplicou a edição sobre texto novo');
        } catch (ApiException $e) {
            $this->assertStringContainsString('mudou', $e->getMessage());
        }

        $this->assertSame('Texto da outra pessoa', Aula::query()->findOrFail($id)->conteudo);
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
            $this->assertSame('Texto 1', Aula::query()->findOrFail($id)->conteudo);
        }
    }

    public function test_aula_sem_texto_tem_versao_estavel(): void
    {
        $this->assertSame(CourseService::versaoDoConteudo(null), CourseService::versaoDoConteudo(''));
        $this->assertNotSame(CourseService::versaoDoConteudo('a'), CourseService::versaoDoConteudo('b'));
    }
}
