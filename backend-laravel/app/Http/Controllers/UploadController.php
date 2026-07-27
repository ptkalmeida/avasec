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
}
