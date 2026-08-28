<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Files;
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
        config(['uploads.root' => $this->tmpRoot]);
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
        $u = DB::table('User')->where('role', 'student')->where('status', 'active')->first(['id', 'name']);
        $this->assertNotNull($u);
        $id = $u->id;
        $name = $u->name;

        return Jwt::issue($u->id, $u->name, 'student');
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
        $token = $this->studentToken();
        $file = UploadedFile::fake()->createWithContent('evil.exe', 'MZ binary');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_content_not_matching_extension_is_rejected(): void
    {
        $token = $this->studentToken();
        // Conteúdo PNG com extensão .pdf -> magic bytes não batem -> 400.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('fake.pdf', $bytes);

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_public_upload_returns_static_url_and_persists(): void
    {
        $token = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('material.png')], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('visibility', 'public');
        $this->assertStringStartsWith('/uploads/', $response->json('url'));
        // Arquivo realmente gravado no diretório público.
        $name = basename($response->json('url'));
        $this->assertFileExists($this->tmpRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$name);
        $this->assertDatabaseHas('StoredFile', ['id' => $name, 'visibility' => 'public']);
    }

    public function test_public_file_is_served_statically_without_auth(): void
    {
        $token = $this->studentToken();

        $upload = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('material.png')], ['Accept' => 'application/json']);
        $url = $upload->json('url');

        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_public_file_is_cacheable_so_it_does_not_hit_php_on_every_load(): void
    {
        // O nome gravado nunca é reaproveitado, então a URL pode ser imutável. Sem
        // max-age o navegador revalidava a cada carga e cada revalidação subia o PHP.
        $token = $this->studentToken();

        $upload = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/upload', ['file' => $this->realPng('cacheavel.png')], ['Accept' => 'application/json']);

        $cacheControl = (string) $this->get($upload->json('url'))->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
    }

    public function test_public_file_revalidation_answers_304_without_resending_the_body(): void
    {
        $token = $this->studentToken();
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
        $up = $this->post('/api/upload?visibility=private', ['file' => $this->realPng('entrega.png')], $auth($ownerToken));
        $up->assertStatus(201)->assertJsonPath('visibility', 'private');
        $fileUrl = $up->json('url'); // /api/files/<id>
        $this->assertStringStartsWith('/api/files/', $fileUrl);

        // Dono baixa: 200.
        $this->get($fileUrl, $auth($ownerToken))->assertOk();

        // Outro aluno REAL e ativo (não dono, não staff): 403. (Precisa existir no banco,
        // senão o middleware `active` responderia 401 por conta inexistente.)
        $admin = DB::table('User')->where('role', 'admin')->first();
        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Outro Aluno '.uniqid(),
            'email' => 'outro-'.uniqid().'@example.com',
            'password' => 'senha123456',
            'role' => 'student',
            'cpf' => $this->makeCpf(),
        ], $auth(Jwt::issue($admin->id, $admin->name, 'admin')));
        $otherToken = Jwt::issue($reg->json('user.id'), $reg->json('user.name'), 'student');
        $this->get($fileUrl, $auth($otherToken))->assertStatus(403);

        // Instrutor (staff): 200.
        $inst = DB::table('User')->where('role', 'instructor')->where('status', 'active')->first(['id', 'name']);
        $this->get($fileUrl, $auth(Jwt::issue($inst->id, $inst->name, 'instructor')))->assertOk();

        // Sem token: 401.
        $this->get($fileUrl, $json)->assertStatus(401);
    }
}
