<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ArquivoArmazenado;
use App\Support\CourseAccess;
use App\Support\InstructorScope;
use Carbon\CarbonImmutable;
use finfo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload de arquivos — espelha src/server/upload.ts. Valida a extensão e confere os
 * magic bytes reais (finfo) contra o tipo esperado; grava com nome gerado no servidor
 * (nunca reaproveita o nome do cliente); separa público (servido estaticamente) de
 * privado (só via download autorizado). Registra tudo em ArquivoArmazenado.
 */
final class UploadService
{
    /**
     * @return array{url:string, nome_arquivo:string, tamanho:string, visibilidade:string}
     */
    public function store(UploadedFile $file, string $visibility, string $dono_usuario_id): array
    {
        $visibility = $visibility === 'private' ? 'private' : 'public';

        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = config('uploads.allowed_types');
        $allowedMimes = is_array($allowed) && isset($allowed[$ext]) && is_array($allowed[$ext]) ? $allowed[$ext] : null;
        if ($allowedMimes === null) {
            throw ApiException::validation('Tipo de arquivo não permitido: '.($ext !== '' ? ".$ext" : '(sem extensão)'));
        }

        $maxSizeMb = config('uploads.max_size_mb');
        $maxBytes = (is_numeric($maxSizeMb) ? (int) $maxSizeMb : 10) * 1024 * 1024;
        $size = (int) $file->getSize();
        if ($size > $maxBytes) {
            throw new ApiException(400, 'UPLOAD_ERROR', 'Arquivo excede o tamanho máximo permitido.');
        }

        // Magic bytes reais do conteúdo — não confia no Content-Type do cliente.
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new ApiException(400, 'UPLOAD_ERROR', 'Falha ao ler o arquivo enviado.');
        }
        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
        if (! in_array($detected, $allowedMimes, true)) {
            throw ApiException::validation('O conteúdo do arquivo não corresponde a um '.strtoupper($ext).' válido.');
        }

        // Nome gerado no servidor — fecha path traversal / sobrescrita.
        $safeName = CarbonImmutable::now()->getTimestampMs().'-'.bin2hex(random_bytes(8)).'.'.$ext;
        // Pelo Laravel Filesystem (Norma TI-SECEC C.5.2): o disco cria a pasta que
        // faltar e aplica a permissão da visibilidade. Com `throw` ligado no disco,
        // falha de escrita vira exceção, e nada é registrado sem o arquivo existir.
        $this->disco($visibility)->put($safeName, $contents);

        ArquivoArmazenado::query()->create([
            'id' => $safeName,
            'nome_original' => mb_substr($file->getClientOriginalName(), 0, 190),
            'visibilidade' => $visibility,
            'dono_usuario_id' => $dono_usuario_id,
            'criado_em' => CarbonImmutable::now(),
        ]);

        return [
            'url' => $visibility === 'private' ? "/api/arquivos/{$safeName}" : "/uploads/{$safeName}",
            'nome_arquivo' => $file->getClientOriginalName(),
            'tamanho' => number_format($size / (1024 * 1024), 2, '.', '').' MB',
            'visibilidade' => $visibility,
        ];
    }

    /**
     * Resolve caminho físico de um arquivo para download autorizado, aplicando as mesmas
     * regras do Node (anti-traversal, existência, autorização por dono/staff).
     *
     * Devolve o DISCO e a CHAVE, não um caminho físico: o controlador baixa pelo
     * disco, o que funciona igual com o disco local de hoje e com o S3/MinIO de
     * amanhã (Norma TI-SECEC C.5.2).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{disco:FilesystemAdapter, chave:string, mime:string, nome_original:string}
     */
    public function resolveForDownload(string $id, array $requester): array
    {
        if (str_contains($id, '/') || str_contains($id, '\\') || str_contains($id, '..')) {
            throw ApiException::validation('Identificador de arquivo inválido.');
        }

        $record = ArquivoArmazenado::query()->find($id);
        if ($record === null) {
            throw ApiException::notFound('Arquivo não encontrado.');
        }

        if ($record->visibilidade === 'private' && ! $this->podeBaixar($record, $requester)) {
            throw ApiException::forbidden('Você não tem permissão para acessar este arquivo.');
        }

        $disco = $this->disco($record->visibilidade);
        if (! $disco->fileExists($record->id)) {
            throw ApiException::notFound('Arquivo não encontrado no armazenamento.');
        }

        $ext = strtolower(pathinfo($record->id, PATHINFO_EXTENSION));
        $mime = config("uploads.mime_by_ext.$ext", 'application/octet-stream');

        return [
            'disco' => $disco,
            'chave' => $record->id,
            'mime' => is_string($mime) ? $mime : 'application/octet-stream',
            'nome_original' => $record->nome_original,
        ];
    }

    /**
     * Quem pode baixar um arquivo PRIVADO.
     *
     * Antes bastava ser instrutor: `$isStaff` valia para qualquer um deles, sem escopo,
     * então um instrutor baixava a entrega privada de aluno de turma alheia. Arquivo
     * privado aqui é entrega de atividade — documento pessoal, às vezes com dado
     * sensível. O escopo agora é o mesmo do resto do sistema: instrutor alcança quem
     * é aluno de um curso que ele leciona.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    private function podeBaixar(ArquivoArmazenado $record, array $requester): bool
    {
        if ($record->dono_usuario_id === $requester['sub']) {
            return true;
        }

        $role = $requester['papel'] ?? null;
        if ($role === 'admin') {
            return true;
        }
        if ($role !== 'instructor' || ! is_string($record->dono_usuario_id) || $record->dono_usuario_id === '') {
            return false;
        }

        // Cursos do dono do arquivo, vistos como aluno — reaproveita CourseAccess em vez
        // de reescrever a consulta de vínculo (matrícula + concluídos + admissão aprovada).
        $cursosDoDono = CourseAccess::accessibleCourseIds([
            'sub' => $record->dono_usuario_id,
            'nome' => '',
            'papel' => 'student',
        ]);

        return array_intersect($cursosDoDono, InstructorScope::courseIds($requester)) !== [];
    }

    /**
     * Resolve caminho físico de um arquivo PÚBLICO para servir estaticamente em
     * /uploads/<nome> (equivalente ao express.static do Node legado). Sem checagem de
     * dono/ArquivoArmazenado — arquivos públicos, incluindo os colocados manualmente na pasta
     * (ex.: vídeo de teste), são acessíveis por qualquer um, como no comportamento antigo.
     *
     * @return array{path:string, mime:string}
     */
    public function resolvePublicFile(string $filename): array
    {
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            throw ApiException::notFound('Arquivo não encontrado.');
        }

        /*
         * O público continua sendo servido pelo CAMINHO do disco local: é o que
         * mantém o Range (o <video> avança no arquivo) e o 304 do cache. Com o
         * MinIO, arquivo público passa a sair do próprio storage ou por URL
         * temporária — muda a forma de servir, não a regra (plano 11, item F3).
         */
        $disco = $this->disco('public');
        if (! $disco->fileExists($filename)) {
            throw ApiException::notFound('Arquivo não encontrado.');
        }
        $path = $disco->path($filename);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = config("uploads.mime_by_ext.$ext", 'application/octet-stream');

        return [
            'path' => $path,
            'mime' => is_string($mime) ? $mime : 'application/octet-stream',
        ];
    }

    /** Disco de cada visibilidade (config/filesystems.php). */
    public static function nomeDoDisco(string $visibility): string
    {
        return $visibility === 'private' ? 'privado' : 'publico';
    }

    private function disco(string $visibility): FilesystemAdapter
    {
        $disco = Storage::disk(self::nomeDoDisco($visibility));
        assert($disco instanceof FilesystemAdapter);

        return $disco;
    }
}
