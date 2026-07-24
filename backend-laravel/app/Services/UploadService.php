<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use finfo;
use Illuminate\Http\UploadedFile;

/**
 * Upload de arquivos — espelha src/server/upload.ts. Valida a extensão e confere os
 * magic bytes reais (finfo) contra o tipo esperado; grava com nome gerado no servidor
 * (nunca reaproveita o nome do cliente); separa público (servido estaticamente) de
 * privado (só via download autorizado). Registra tudo em StoredFile.
 */
final class UploadService
{
    /**
     * @return array{url:string, fileName:string, size:string, visibility:string}
     */
    public function store(UploadedFile $file, string $visibility, string $ownerUserId): array
    {
        $visibility = $visibility === 'private' ? 'private' : 'public';

        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = config('uploads.allowed_types');
        if (! isset($allowed[$ext])) {
            throw ApiException::validation('Tipo de arquivo não permitido: '.($ext !== '' ? ".$ext" : '(sem extensão)'));
        }

        $maxBytes = ((int) config('uploads.max_size_mb')) * 1024 * 1024;
        $size = (int) $file->getSize();
        if ($size > $maxBytes) {
            throw new ApiException(400, 'UPLOAD_ERROR', 'Arquivo excede o tamanho máximo permitido.');
        }

        // Magic bytes reais do conteúdo — não confia no Content-Type do cliente.
        $contents = file_get_contents($file->getRealPath());
        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
        if (! in_array($detected, $allowed[$ext], true)) {
            throw ApiException::validation('O conteúdo do arquivo não corresponde a um '.strtoupper($ext).' válido.');
        }

        // Nome gerado no servidor — fecha path traversal / sobrescrita.
        $safeName = CarbonImmutable::now()->getTimestampMs().'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $targetDir = $this->dir($visibility);
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0o755, true);
        }
        file_put_contents($targetDir.DIRECTORY_SEPARATOR.$safeName, $contents);
        @chmod($targetDir.DIRECTORY_SEPARATOR.$safeName, 0o644);

        StoredFile::query()->create([
            'id' => $safeName,
            'originalName' => mb_substr($file->getClientOriginalName(), 0, 190),
            'visibility' => $visibility,
            'ownerUserId' => $ownerUserId,
            'createdAt' => CarbonImmutable::now(),
        ]);

        return [
            'url' => $visibility === 'private' ? "/api/files/{$safeName}" : "/uploads/{$safeName}",
            'fileName' => $file->getClientOriginalName(),
            'size' => number_format($size / (1024 * 1024), 2, '.', '').' MB',
            'visibility' => $visibility,
        ];
    }

    /**
     * Resolve caminho físico de um arquivo para download autorizado, aplicando as mesmas
     * regras do Node (anti-traversal, existência, autorização por dono/staff).
     *
     * @param  array{sub:string,name:mixed,role:mixed}  $requester
     * @return array{path:string, mime:string, originalName:string}
     */
    public function resolveForDownload(string $id, array $requester): array
    {
        if (str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw ApiException::validation('Identificador de arquivo inválido.');
        }

        $record = StoredFile::query()->find($id);
        if ($record === null) {
            throw ApiException::notFound('Arquivo não encontrado.');
        }

        $isOwner = $record->ownerUserId === $requester['sub'];
        $isStaff = in_array($requester['role'] ?? null, ['instructor', 'admin'], true);
        if ($record->visibility === 'private' && ! $isOwner && ! $isStaff) {
            throw ApiException::forbidden('Você não tem permissão para acessar este arquivo.');
        }

        $path = $this->dir($record->visibility).DIRECTORY_SEPARATOR.$record->id;
        if (! is_file($path)) {
            throw ApiException::notFound('Arquivo não encontrado no armazenamento.');
        }

        $ext = strtolower(pathinfo($record->id, PATHINFO_EXTENSION));
        $mime = config("uploads.mime_by_ext.$ext", 'application/octet-stream');

        return ['path' => $path, 'mime' => $mime, 'originalName' => $record->originalName];
    }

    private function dir(string $visibility): string
    {
        return rtrim((string) config('uploads.root'), '/\\').DIRECTORY_SEPARATOR.($visibility === 'private' ? 'private' : 'public');
    }
}
