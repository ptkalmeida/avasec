<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class UploadController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(private readonly UploadService $uploads) {}

    public function store(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw ApiException::validation('Nenhum arquivo enviado.');
        }

        $visibility = $request->query('visibility') === 'private' ? 'private' : 'public';
        $result = $this->uploads->store($file, $visibility, $this->requester($request)['sub']);

        return response()->json($result, 201);
    }

    public function download(Request $request, string $id): BinaryFileResponse
    {
        $resolved = $this->uploads->resolveForDownload($id, $this->requester($request));

        // 'attachment' força download — arquivo de terceiros nunca é renderizado no app.
        return response()->download($resolved['path'], $resolved['originalName'], [
            'Content-Type' => $resolved['mime'],
        ]);
    }

    /**
     * Serve estaticamente um arquivo PÚBLICO em /uploads/<nome> — sem autenticação,
     * equivalente ao express.static do Node legado (rota /uploads). BinaryFileResponse
     * do Symfony já responde a Range requests, necessário para o <video> nativo buscar
     * (seek) no arquivo.
     */
    public function servePublic(Request $request, string $filename): BinaryFileResponse
    {
        // Rota fora de /api/* — o handler global só serializa ApiException como JSON
        // para caminhos api/*, então aqui convertemos direto para o status HTTP puro.
        try {
            $resolved = $this->uploads->resolvePublicFile($filename);
        } catch (ApiException $e) {
            abort($e->status, $e->getMessage());
        }

        // Cache agressivo e seguro: o nome gravado é timestamp + 8 bytes aleatórios
        // (UploadService::store), nunca reaproveitado — o conteúdo de uma URL não muda,
        // trocar o arquivo gera outra URL. Sem isto a resposta saía só com
        // "Cache-Control: public", sem max-age: o navegador caía em cache heurístico e
        // revalidava a toda hora, e cada revalidação sobe o PHP inteiro para devolver um
        // PNG. Sem ETag de propósito: gerá-lo exige ler o arquivo inteiro a cada
        // request, caro nos vídeos — o Last-Modified basta para revalidar.
        $response = response()->file($resolved['path'], [
            'Content-Type' => $resolved['mime'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);

        // O Laravel não checa If-Modified-Since sozinho: sem esta chamada, uma
        // revalidação (recarga forçada, cache expirado) reenviava o arquivo inteiro em
        // 200. isNotModified() converte a resposta em 304 sem corpo quando cabe.
        $response->isNotModified($request);

        return $response;
    }
}
