<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\CourseAccess;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GeneratesCpf;
use Tests\TestCase;

/**
 * Etapa 4 — Uploads. Espelha src/server/upload.ts: validação de magic bytes, split
 * público/privado, autorização de download privado. Arquivos gravados num diretório
 * temporário isolado (não polui o uploads/ compartilhado). DB em transação revertida.
 */
final class UploadTest extends TestCase
{
    use DatabaseTransactions;
    use GeneratesCpf;

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = storage_path('framework/testing/uploads-'.uniqid());
        // Os discos de upload (config/filesystems.php) apontam para a pasta temporária:
        // o teste nunca grava na pasta real de uploads.
        config([
            'filesystems.disks.publico.root' => $this->tmpRoot.DIRECTORY_SEPARATOR.'public',
            'filesystems.disks.privado.root' => $this->tmpRoot.DIRECTORY_SEPARATOR.'private',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpRoot)) {
            Files::deleteDirectory($this->tmpRoot);
        }
        parent::tearDown();
    }

    private function studentToken(?string &$id = null, ?string &$name = null): string
    {
        $u = DB::table('usuario')->where('papel', 'student')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotNull($u);
        $id = $u->id;
        $name = $u->nome;

        return Jwt::issue($u->id, $u->nome, 'student');
    }

    private function instructorToken(): string
    {
        $u = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotNull($u);

        return Jwt::issue($u->id, $u->nome, 'instructor');
    }

    public function test_aluno_nao_publica_na_pasta_publica_mas_envia_privado(): void
    {
        // ADR 022: a pasta pública é aberta a qualquer visitante; aluno só envia a própria entrega, privada.
        Storage::fake('publico');
        Storage::fake('privado');
        $auth = ['Authorization' => 'Bearer '.$this->studentToken(), 'Accept' => 'application/json'];

        $this->post('/api/upload', ['file' => $this->realPng('golpe.png')], $auth)->assertStatus(403);
        $this->post('/api/upload?visibilidade=public', ['file' => $this->realPng('golpe.png')], $auth)->assertStatus(403);
        $this->assertSame([], Storage::disk('publico')->allFiles());

        $this->post('/api/upload?visibilidade=private', ['file' => $this->realPng('entrega.png')], $auth)->assertStatus(201);
    }

    public function test_instrutor_e_admin_continuam_publicando(): void
    {
        Storage::fake('publico');
        $admin = DB::table('usuario')->where('papel', 'admin')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotNull($admin);

        foreach ([$this->instructorToken(), Jwt::issue($admin->id, $admin->nome, 'admin')] as $token) {
            $this->post('/api/upload', ['file' => $this->realPng('capa.png')], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
                ->assertStatus(201)->assertJsonPath('visibilidade', 'public');
        }
    }

    private function realPng(string $name): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_upload_without_token_is_401(): void
    {
        $this->post('/api/upload', ['file' => $this->realPng('a.png')], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $token = $this->instructorToken();
        $file = UploadedFile::fake()->createWithContent('evil.exe', 'MZ binary');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_content_not_matching_extension_is_rejected(): void
    {
        $token = $this->instructorToken();
        // Conteúdo PNG com extensão .pdf -> magic bytes não batem -> 400.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('fake.pdf', $bytes);

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_public_upload_returns_static_url_and_persists(): void
    {
        $token = $this->instructorToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('material.png')], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('visibilidade', 'public');
        $this->assertStringStartsWith('/uploads/', $response->json('url'));
        // Arquivo realmente gravado no diretório público.
        $name = basename($response->json('url'));
        $this->assertFileExists($this->tmpRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$name);
        $this->assertDatabaseHas('arquivo_armazenado', ['id' => $name, 'visibilidade' => 'public']);
    }

    public function test_cada_visibilidade_grava_no_seu_disco_do_laravel_filesystem(): void
    {
        // Norma TI-SECEC C.5.2: o arquivo passa pelo Laravel Filesystem. É isto que
        // deixa a troca para MinIO ser só de configuração do disco.
        Storage::fake('publico');
        Storage::fake('privado');
        $auth = ['Authorization' => 'Bearer '.$this->instructorToken(), 'Accept' => 'application/json'];

        $publico = $this->post('/api/upload', ['file' => $this->realPng('capa.png')], $auth)->assertStatus(201);
        $privado = $this->post('/api/upload?visibilidade=private', ['file' => $this->realPng('entrega.png')], $auth)->assertStatus(201);

        $nomePublico = basename((string) $publico->json('url'));
        $nomePrivado = basename((string) $privado->json('url'));
        Storage::disk('publico')->assertExists($nomePublico);
        Storage::disk('privado')->assertExists($nomePrivado);
        // E nunca no disco da outra visibilidade.
        Storage::disk('publico')->assertMissing($nomePrivado);
        Storage::disk('privado')->assertMissing($nomePublico);
    }

    public function test_public_file_is_served_statically_without_auth(): void
    {
        $token = $this->instructorToken();

        $upload = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('material.png')], ['Accept' => 'application/json']);
        $url = $upload->json('url');

        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_public_file_is_cacheable_so_it_does_not_hit_php_on_every_load(): void
    {
        // O nome gravado nunca é reaproveitado, então a URL pode ser imutável. Sem
        // max-age o navegador revalidava a cada carga e cada revalidação subia o PHP.
        $token = $this->instructorToken();

        $upload = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('cacheavel.png')], ['Accept' => 'application/json']);

        $cacheControl = (string) $this->get($upload->json('url'))->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
    }

    public function test_public_file_revalidation_answers_304_without_resending_the_body(): void
    {
        $token = $this->instructorToken();
        $upload = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('revalida.png')], ['Accept' => 'application/json']);
        $url = $upload->json('url');

        $lastModified = (string) $this->get($url)->assertOk()->headers->get('Last-Modified');
        $this->assertNotSame('', $lastModified);

        // Sem a checagem explícita de If-Modified-Since, o Laravel reenviava o arquivo
        // inteiro com 200 em cada revalidação.
        $this->withHeader('If-Modified-Since', $lastModified)->get($url)->assertStatus(304);
    }

    public function test_static_upload_route_rejects_traversal_and_missing_file(): void
    {
        $this->get('/uploads/'.rawurlencode('../../.env'))->assertStatus(404);
        $this->get('/uploads/arquivo-inexistente.png')->assertStatus(404);
    }

    public function test_private_download_authorization(): void
    {
        // Headers inline por requisição (withHeader persiste entre requests no mesmo teste).
        $json = ['Accept' => 'application/json'];
        $auth = fn (string $token) => ['Accept' => 'application/json', 'Authorization' => "Bearer $token"];

        $ownerId = null;
        $ownerName = null;
        $ownerToken = $this->studentToken($ownerId, $ownerName);

        // Dono envia arquivo privado.
        $up = $this->post('/api/upload?visibilidade=private', ['file' => $this->realPng('entrega.png')], $auth($ownerToken));
        $up->assertStatus(201)->assertJsonPath('visibilidade', 'private');
        $url_arquivo = $up->json('url'); // /api/arquivos/<id>
        $this->assertStringStartsWith('/api/arquivos/', $url_arquivo);

        // Dono baixa: 200.
        $this->get($url_arquivo, $auth($ownerToken))->assertOk();

        // Outro aluno REAL e ativo (não dono, não staff): 403. (Precisa existir no banco,
        // senão o middleware `active` responderia 401 por conta inexistente.)
        $admin = DB::table('usuario')->where('papel', 'admin')->first();
        $reg = $this->postJson('/api/autenticacao/cadastro', [
            'nome' => 'Outro Aluno '.uniqid(),
            'email' => 'outro-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'papel' => 'student',
            'cpf' => $this->makeCpf(),
        ], $auth(Jwt::issue($admin->id, $admin->nome, 'admin')));
        $otherToken = Jwt::issue($reg->json('usuario.id'), $reg->json('usuario.nome'), 'student');
        $this->get($url_arquivo, $auth($otherToken))->assertStatus(403);

        // Instrutor do curso do dono: 200. (Não é "por ser staff": o escopo é o vínculo
        // com a turma — ver o caso seguinte, que era o furo.)
        $inst = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')->first(['id', 'nome']);
        $this->assertNotEmpty(
            array_intersect(
                DB::table('curso')->where('instrutor_id', $inst->id)->pluck('id')->all(),
                CourseAccess::accessibleCourseIds(['sub' => $ownerId, 'nome' => '', 'papel' => 'student'])
            ),
            'Dono do arquivo não é aluno deste instrutor — o 200 abaixo não provaria escopo.'
        );
        $this->get($url_arquivo, $auth(Jwt::issue($inst->id, $inst->nome, 'instructor')))->assertOk();

        // Sem token: 401.
        $this->get($url_arquivo, $json)->assertStatus(401);
    }

    // Entrega privada é documento pessoal do aluno. Antes bastava ter papel de
    // instrutor — sem escopo algum — para baixar a de qualquer aluno da escola.
    public function test_instructor_of_another_course_cannot_download_private_file(): void
    {
        $auth = fn (string $token) => ['Accept' => 'application/json', 'Authorization' => "Bearer $token"];

        $ownerId = null;
        $ownerName = null;
        $ownerToken = $this->studentToken($ownerId, $ownerName);

        $up = $this->post('/api/upload?visibilidade=private', ['file' => $this->realPng('entrega.png')], $auth($ownerToken));
        $url_arquivo = $up->assertStatus(201)->json('url');

        // Instrutor sem curso nenhum atribuído: não alcança aluno algum.
        $alheio = DB::table('usuario')->where('papel', 'instructor')->where('status', 'active')
            ->whereNotIn('id', DB::table('curso')->distinct()->select('instrutor_id'))
            ->first(['id', 'nome']);
        if ($alheio === null) {
            $this->markTestSkipped('Seed sem instrutor fora da titularidade dos cursos.');
        }

        $this->get($url_arquivo, $auth(Jwt::issue($alheio->id, $alheio->nome, 'instructor')))
            ->assertStatus(403);

        // Admin segue irrestrito (coordenação).
        $admin = DB::table('usuario')->where('papel', 'admin')->first(['id', 'nome']);
        $this->get($url_arquivo, $auth(Jwt::issue($admin->id, $admin->nome, 'admin')))->assertOk();
    }
}
