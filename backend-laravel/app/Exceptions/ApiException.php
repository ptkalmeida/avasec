<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Erro de API padronizado, espelhando o ApiError do backend Node
 * (src/server/utils/ApiError.ts). O handler central serializa como
 * { error: true, code, message } — mesmo contrato consumido pelo frontend.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unauthorized(string $message = 'Autenticação necessária para acessar este recurso.'): self
    {
        return new self(401, 'UNAUTHORIZED', $message);
    }

    public static function forbidden(string $message = 'Acesso não permitido.'): self
    {
        return new self(403, 'FORBIDDEN', $message);
    }

    public static function accountBlocked(): self
    {
        return new self(
            403,
            'ACCOUNT_BLOCKED',
            'Seu acesso à Escola Estadual da Cultura foi suspenso. Entre em contato com a coordenação para mais informações.',
        );
    }

    public static function accountPending(): self
    {
        return new self(
            403,
            'ACCOUNT_PENDING_CONFIRMATION',
            'Seu cadastro ainda está aguardando confirmação. Você poderá acessar as áreas internas assim que for homologado pela coordenação.',
        );
    }

    public static function featureDisabled(): self
    {
        return new self(
            404,
            'FEATURE_DISABLED',
            'Esta funcionalidade não está disponível nesta versão da plataforma.',
        );
    }

    public static function validation(string $message): self
    {
        return new self(400, 'VALIDATION_ERROR', $message);
    }

    public static function notFound(string $message = 'Recurso não encontrado.'): self
    {
        return new self(404, 'NOT_FOUND', $message);
    }

    public static function tooManyRequests(string $message = 'Muitas tentativas em um curto período. Tente novamente mais tarde.'): self
    {
        return new self(429, 'RATE_LIMITED', $message);
    }
}
