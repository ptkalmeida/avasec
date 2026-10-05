<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger as LaravelLogger;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Carimba `app` e `env` em todo registro de log.
 *
 * Norma Técnica TI-SECEC (C.7.2, F.14): o coletor (Grafana Alloy) precisa saber
 * de que aplicação e de que ambiente veio cada linha, para consultas como
 * `aplicacao = ava, ambiente = prd`. Sem isto, logs de HML e PRD de vários
 * sistemas chegariam misturados ao Loki.
 */
final class IdentificaAplicacao
{
    public function __invoke(LaravelLogger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof Logger) {
            self::aplicar($monolog);
        }
    }

    /** Separado do `__invoke` para testar sem passar pelo Laravel. */
    public static function aplicar(Logger $logger): void
    {
        $aplicacao = config('logging.aplicacao');
        $ambiente = config('app.env');

        $logger->pushProcessor(static fn (LogRecord $registro): LogRecord => $registro->with(extra: [
            ...$registro->extra,
            'app' => is_string($aplicacao) && $aplicacao !== '' ? $aplicacao : 'ava',
            'env' => is_string($ambiente) ? $ambiente : 'desconhecido',
        ]));
    }
}
