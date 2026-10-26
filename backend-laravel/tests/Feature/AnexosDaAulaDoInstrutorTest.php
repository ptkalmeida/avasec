<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonDocument;
use App\Models\User;
use App\Services\CourseService;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Os anexos de uma aula — vincular arquivo ou link, e desvincular.
 *
 * O que prendem: que o anexo novo é a ÚNICA escrita (o React regravava a lista
 * inteira de anexos de todas as aulas), que o endereço passa pela mesma regra de
 * `href` da API (nada de `javascript:`), que desvincular NÃO apaga (ADR 12), e
 * que o arquivo enviado passa pelo mesmo `UploadService` da API.
 */
final class AnexosDaAulaDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return ['id' => $id, 'name' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    /** @return array{0:Course,1:Lesson,2:Lesson} */
    private function cursoComDuasAulas(string $instructorId, string $titulo = 'Curso Dos Anexos'): array
    {
        $curso = Course::query()->create([
            'id' => 'course-anexo-'.uniqid(),
            'title' => $titulo,
            'description' => 'Descrição longa o bastante para o validador aceitar.',
            'category' => 'Teste',
            'thumbnail' => 'sem-imagem.png',
            'instructorId' => $instructorId,
            'instructorName' => 'Instrutor',
        ]);
        Course::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        $aulas = [];
        foreach ([1, 2] as $i) {
            $aulas[] = Lesson::query()->create([
                'id' => 'lesson-anexo-'.$curso->id.'-'.$i,
                'courseId' => $curso->id,
                'title' => "Aula {$i}",
                'duration' => '30 min',
                'content' => "Texto {$i}",
                'lesson_order' => $i,
            ]);
        }

        return [$curso->refresh(), $aulas[0], $aulas[1]];
    }

    private function endereco(Course $curso, Lesson $aula, string $resto = ''): string
    {
        return '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id.$resto;
    }

    private function png(string $nome): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nome, $bytes);
    }

    // ---- vincular um link -----------------------------------------------------

    public function test_vincula_um_link_e_a_pagina_mostra_o_anexo(): void
    {
        $eu = $this->instrutor('Gestor Anexo');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos'), [
                'titulo' => 'Leitura complementar',
                'tipo' => 'url',
                'url' => 'https://exemplo.com/leitura',
            ])
            ->assertRedirect($this->endereco($curso, $aula).'#anexos')
            ->assertSessionHas('avisoDoCurso');

        $doc = LessonDocument::query()->where('lessonId', $aula->id)->firstOrFail();
        $this->assertSame('Leitura complementar', $doc->title);
        $this->assertSame('url', $doc->type);
        $this->assertSame('https://exemplo.com/leitura', $doc->url);
        $this->assertNull($doc->size);

        $this->como($eu['token'])->get($this->endereco($curso, $aula))
            ->assertOk()->assertSee('Material de apoio (1)')->assertSee('Leitura complementar');
    }

    public function test_o_tipo_automatico_reconhece_o_google_drive(): void
    {
        $eu = $this->instrutor('Gestor Drive');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        $this->como($eu['token'])->post($this->endereco($curso, $aula, '/anexos'), [
            'titulo' => 'Planilha', 'tipo' => 'auto', 'url' => 'https://docs.google.com/spreadsheets/d/abc',
        ]);
        $this->como($eu['token'])->post($this->endereco($curso, $aula, '/anexos'), [
            'titulo' => 'Site', 'tipo' => 'auto', 'url' => 'https://exemplo.com',
        ]);

        $tipos = LessonDocument::query()->where('lessonId', $aula->id)->orderBy('title')->pluck('type', 'title')->all();
        $this->assertSame(['Planilha' => 'drive', 'Site' => 'url'], $tipos);
    }

    public function test_so_a_aula_aberta_recebe_o_anexo(): void
    {
        $eu = $this->instrutor('Gestor Isolado');
        [$curso, $aula, $outra] = $this->cursoComDuasAulas($eu['id']);
        LessonDocument::query()->create(['id' => 'doc-previo', 'lessonId' => $outra->id, 'title' => 'Da outra', 'type' => 'url', 'url' => 'https://exemplo.com/a']);

        $this->como($eu['token'])->post($this->endereco($curso, $aula, '/anexos'), ['titulo' => 'Novo', 'tipo' => 'url', 'url' => 'https://exemplo.com/b']);

        $this->assertSame(1, LessonDocument::query()->where('lessonId', $aula->id)->count());
        $this->assertSame(['Da outra'], LessonDocument::query()->where('lessonId', $outra->id)->pluck('title')->all());
    }

    public function test_recusa_o_que_o_painel_antigo_recusava_e_reabre_a_zona(): void
    {
        $eu = $this->instrutor('Gestor Recusa');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        foreach ([
            'sem título' => ['titulo' => '  ', 'tipo' => 'url', 'url' => 'https://exemplo.com'],
            'sem endereço' => ['titulo' => 'X', 'tipo' => 'url', 'url' => ''],
            'esquema perigoso' => ['titulo' => 'X', 'tipo' => 'url', 'url' => 'javascript:alert(1)'],
            'tipo inventado' => ['titulo' => 'X', 'tipo' => 'virus', 'url' => 'https://exemplo.com'],
            'tamanho gigante' => ['titulo' => 'X', 'tipo' => 'url', 'url' => 'https://exemplo.com', 'tamanho' => str_repeat('9', 31)],
        ] as $caso => $campos) {
            $this->como($eu['token'])
                ->post($this->endereco($curso, $aula, '/anexos'), $campos)
                ->assertRedirect($this->endereco($curso, $aula).'?editar=anexos#anexos')
                ->assertSessionHasErrors('anexos');
            $this->assertSame(0, LessonDocument::query()->where('lessonId', $aula->id)->count(), $caso);
        }
    }

    // ---- vincular um arquivo -------------------------------------------------

    public function test_o_arquivo_enviado_vira_o_anexo_com_titulo_tipo_e_tamanho_deduzidos(): void
    {
        Storage::fake('publico');
        $eu = $this->instrutor('Gestor Arquivo');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos'), ['arquivo' => $this->png('slides_da-aula.png'), 'tipo' => 'auto'])
            ->assertRedirect($this->endereco($curso, $aula).'#anexos');

        $doc = LessonDocument::query()->where('lessonId', $aula->id)->firstOrFail();
        $this->assertSame('Slides Da Aula', $doc->title);
        $this->assertSame('outro', $doc->type);
        $this->assertStringStartsWith('/uploads/', $doc->url);
        $this->assertStringEndsWith(' MB', (string) $doc->size);
        Storage::disk('publico')->assertExists(basename($doc->url));
    }

    public function test_arquivo_de_tipo_proibido_e_recusado_pelo_mesmo_servico_da_api(): void
    {
        Storage::fake('publico');
        $eu = $this->instrutor('Gestor Exe');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos'), ['arquivo' => UploadedFile::fake()->createWithContent('virus.exe', 'MZ binario'), 'tipo' => 'auto'])
            ->assertRedirect($this->endereco($curso, $aula).'?editar=anexos#anexos')
            ->assertSessionHasErrors('anexos');

        $this->assertSame(0, LessonDocument::query()->where('lessonId', $aula->id)->count());
    }

    public function test_com_o_envio_de_arquivos_desligado_o_arquivo_e_recusado_e_o_link_segue(): void
    {
        Storage::fake('publico');
        config(['features.uploadArquivos' => false]);
        $eu = $this->instrutor('Gestor Sem Upload');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos'), ['arquivo' => $this->png('a.png'), 'titulo' => 'A'])
            ->assertSessionHasErrors('anexos');
        $this->como($eu['token'])->get($this->endereco($curso, $aula).'?editar=anexos')
            ->assertOk()->assertDontSee('Arquivo do seu computador');

        $this->como($eu['token'])->post($this->endereco($curso, $aula, '/anexos'), ['titulo' => 'Link', 'tipo' => 'url', 'url' => 'https://exemplo.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, LessonDocument::query()->where('lessonId', $aula->id)->count());
    }

    // ---- desvincular ------------------------------------------------------------

    public function test_desvincular_nao_apaga_e_registra_quem_e_por_que(): void
    {
        $eu = $this->instrutor('Gestor Desvincula');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);
        LessonDocument::query()->create(['id' => 'doc-x', 'lessonId' => $aula->id, 'title' => 'Sai', 'type' => 'url', 'url' => 'https://exemplo.com/sai']);
        LessonDocument::query()->create(['id' => 'doc-y', 'lessonId' => $aula->id, 'title' => 'Fica', 'type' => 'url', 'url' => 'https://exemplo.com/fica']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos/doc-x/remover'))
            ->assertRedirect($this->endereco($curso, $aula).'?editar=anexos#anexos')
            ->assertSessionHas('avisoDoCurso');

        $this->assertSame(['Fica'], LessonDocument::query()->where('lessonId', $aula->id)->pluck('title')->all());

        $guardado = LessonDocument::query()->withTrashed()->findOrFail('doc-x');
        $this->assertTrue($guardado->estaInativo());
        $this->assertSame($eu['id'], $guardado->inativadoPor);
        $this->assertNotNull($guardado->motivoInativacao);
    }

    public function test_documento_de_outra_aula_nao_e_alcancado_pelo_endereco_desta(): void
    {
        $eu = $this->instrutor('Gestor Cruzado');
        [$curso, $aula, $outra] = $this->cursoComDuasAulas($eu['id']);
        LessonDocument::query()->create(['id' => 'doc-da-outra', 'lessonId' => $outra->id, 'title' => 'Da outra', 'type' => 'url', 'url' => 'https://exemplo.com/o']);

        $this->como($eu['token'])
            ->post($this->endereco($curso, $aula, '/anexos/doc-da-outra/remover'))
            ->assertSessionHasErrors('anexos');

        $this->assertFalse(LessonDocument::query()->withTrashed()->findOrFail('doc-da-outra')->estaInativo());
    }

    // ---- acesso -----------------------------------------------------------------

    public function test_visitante_vai_para_o_login_e_curso_alheio_responde_404(): void
    {
        $this->post('/inst/curso/x/aulas/y/anexos')->assertRedirect('/entrar');
        $this->post('/inst/curso/x/aulas/y/anexos/z/remover')->assertRedirect('/entrar');

        $eu = $this->instrutor('Gestor Um Anexo');
        $outro = $this->instrutor('Gestor Dois Anexo');
        [$alheio, $aula] = $this->cursoComDuasAulas($outro['id'], 'Curso Alheio Dos Anexos');
        LessonDocument::query()->create(['id' => 'doc-alheio', 'lessonId' => $aula->id, 'title' => 'Alheio', 'type' => 'url', 'url' => 'https://exemplo.com/z']);

        $this->como($eu['token'])->post($this->endereco($alheio, $aula, '/anexos'), ['titulo' => 'I', 'tipo' => 'url', 'url' => 'https://exemplo.com'])->assertNotFound();
        $this->como($eu['token'])->post($this->endereco($alheio, $aula, '/anexos/doc-alheio/remover'))->assertNotFound();

        $this->assertSame(1, LessonDocument::query()->where('lessonId', $aula->id)->count());
    }

    // ---- a zona ---------------------------------------------------------------

    public function test_a_zona_aberta_oferece_o_formulario_e_a_lista_com_abrir_e_desvincular(): void
    {
        $eu = $this->instrutor('Gestor Zona');
        [$curso, $aula] = $this->cursoComDuasAulas($eu['id']);
        LessonDocument::query()->create(['id' => 'doc-z', 'lessonId' => $aula->id, 'title' => 'Apostila', 'type' => 'pdf', 'url' => 'https://exemplo.com/a.pdf', 'size' => '2 MB']);

        $this->como($eu['token'])->get($this->endereco($curso, $aula))
            ->assertOk()->assertSee('Apostila')->assertDontSee('Vincular documento');

        $this->como($eu['token'])->get($this->endereco($curso, $aula).'?editar=anexos')
            ->assertOk()
            ->assertSee('Vincular documento')
            ->assertSee('Desvincular')
            ->assertSee('href="https://exemplo.com/a.pdf"', false);
    }

    public function test_o_servico_recusa_anexar_a_aula_de_outro_curso(): void
    {
        $eu = $this->instrutor('Gestor Servico');
        [$curso] = $this->cursoComDuasAulas($eu['id']);
        [, $aulaDeOutroCurso] = $this->cursoComDuasAulas($eu['id'], 'Outro Curso Dos Anexos');

        $this->expectException(ApiException::class);

        app(CourseService::class)->anexarDocumento(
            $curso->id,
            $aulaDeOutroCurso->id,
            ['title' => 'Cruzado', 'type' => 'url', 'url' => 'https://exemplo.com'],
            ['sub' => $eu['id'], 'name' => $eu['name'], 'role' => 'instructor'],
        );
    }
}
