<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Nível de log das negativas de acesso (.ai/planejamento/08).
 *
 * 401/403 são o controle de acesso funcionando, e eram gravados como ERROR com
 * stack trace completo — o laravel.log crescia ~100 MB por dia. Agora viram
 * WARNING sem trace. O que NÃO pode mudar: a resposta HTTP ao cliente, e o
 * registro completo de qualquer falha de verdade.
 */
final class LogDeAcessoNegadoTest extends TestCase
{
    public function test_negativa_401_vira_warning_sem_stack_trace(): void
    {
        Log::spy();

        $this->app->make(ExceptionHandler::class)
            ->report(ApiException::unauthorized('Token de autenticação ausente.'));

        Log::shouldHaveReceived('warning')->once()->with(
            'Token de autenticação ausente.',
            Mockery::on(fn (array $ctx): bool => $ctx['status'] === 401 && ! array_key_exists('exception', $ctx)),
        );
        Log::shouldNotHaveReceived('error');
    }

    public function test_negativa_403_vira_warning_sem_stack_trace(): void
    {
        Log::spy();

        $this->app->make(ExceptionHandler::class)->report(ApiException::forbidden());

        Log::shouldHaveReceived('warning')->once()->with(
            'Acesso não permitido.',
            Mockery::on(fn (array $ctx): bool => $ctx['status'] === 403 && $ctx['code'] === 'FORBIDDEN'),
        );
        Log::shouldNotHaveReceived('error');
    }

    public function test_falha_inesperada_continua_como_error_com_a_excecao(): void
    {
        Log::spy();

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('falha real'));

        Log::shouldHaveReceived('error')->once()->with(
            'falha real',
            Mockery::on(fn (array $ctx): bool => ($ctx['exception'] ?? null) instanceof RuntimeException),
        );
        Log::shouldNotHaveReceived('warning');
    }

    // Fronteira do escopo aprovado: só 401/403 mudaram. As demais ApiException
    // (400, 404, 429) seguem o registro padrão até alguém decidir o contrário.
    public function test_outras_api_exceptions_nao_mudaram_de_nivel(): void
    {
        Log::spy();

        $this->app->make(ExceptionHandler::class)->report(ApiException::notFound());

        Log::shouldHaveReceived('error')->once();
        Log::shouldNotHaveReceived('warning');
    }

    public function test_resposta_http_do_401_nao_mudou_e_o_log_traz_o_caminho(): void
    {
        Log::spy();

        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertExactJson([
                'error' => true,
                'code' => 'UNAUTHORIZED',
                'message' => 'Token de autenticação ausente.',
            ]);

        Log::shouldHaveReceived('warning')->once()->with(
            'Token de autenticação ausente.',
            Mockery::on(fn (array $ctx): bool => $ctx['path'] === 'api/auth/me' && $ctx['method'] === 'GET'),
        );
        Log::shouldNotHaveReceived('error');
    }
}
