<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Logging\IdentificaAplicacao;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

/**
 * Logs no padrão da Norma Técnica TI-SECEC (C.7.2) e prefixo Redis (C.4.3).
 */
final class LogEstruturadoTest extends TestCase
{
    public function test_todo_registro_leva_aplicacao_e_ambiente(): void
    {
        config(['logging.aplicacao' => 'ava', 'app.env' => 'prd']);
        $coletor = new TestHandler;
        $logger = new Logger('teste', [$coletor]);
        IdentificaAplicacao::aplicar($logger);

        $logger->warning('Acesso não permitido.', ['status' => 403]);

        $registro = $coletor->getRecords()[0];
        $this->assertSame('ava', $registro->extra['app']);
        $this->assertSame('prd', $registro->extra['env']);
        // O contexto original não se perde.
        $this->assertSame(403, $registro->context['status']);
    }

    public function test_o_canal_de_container_escreve_json_na_saida_de_erro(): void
    {
        $canal = config('logging.channels.stderr_json');

        $this->assertIsArray($canal);
        $this->assertSame(JsonFormatter::class, $canal['formatter']);
        $this->assertSame('php://stderr', $canal['handler_with']['stream']);
        $this->assertContains(IdentificaAplicacao::class, $canal['tap']);
    }

    public function test_testes_nao_escrevem_no_log_de_desenvolvimento(): void
    {
        // Os testes provocam centenas de 401/403 de propósito; antes iam todos
        // para o storage/logs de desenvolvimento.
        $this->assertSame('descartado', config('logging.default'));
    }

    public function test_prefixo_redis_segue_o_namespace_institucional(): void
    {
        $prefixo = config('database.redis.options.prefix');

        $this->assertIsString($prefixo);
        $this->assertMatchesRegularExpression('/^secec:ava:[a-z]+:$/', $prefixo);
    }
}
