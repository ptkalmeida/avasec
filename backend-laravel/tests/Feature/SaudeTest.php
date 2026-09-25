<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saúde e status no padrão da Norma Técnica TI-SECEC (C.8 e C.8.1).
 * Requer MySQL de dev de pé.
 */
final class SaudeTest extends TestCase
{
    /** Troca a conexão padrão por uma que não conecta (porta fechada). */
    private function derrubarBanco(): void
    {
        config([
            'database.connections.quebrada' => array_merge(
                (array) config('database.connections.mysql'),
                ['host' => '127.0.0.1', 'port' => 1]
            ),
            'database.default' => 'quebrada',
        ]);
        DB::purge('quebrada');
    }

    public function test_live_responde_sem_consultar_dependencia(): void
    {
        // Mesmo com o banco fora, a instância está de pé: o balanceador não pode
        // tirar todas as instâncias do ar por causa de uma dependência.
        $this->derrubarBanco();

        $res = $this->get('/health/live');

        $res->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        // Fora do grupo web: verificação de monitor não abre sessão.
        $this->assertSame([], $res->headers->getCookies());
    }

    public function test_ready_ok_com_banco_e_armazenamento(): void
    {
        $this->get('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'verificacoes' => ['banco' => 'ok', 'armazenamento' => 'ok']]);
    }

    public function test_ready_503_quando_o_banco_cai(): void
    {
        $this->derrubarBanco();

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'indisponivel')
            ->assertJsonPath('verificacoes.banco', 'indisponivel');
    }

    public function test_ready_503_quando_o_armazenamento_nao_existe(): void
    {
        config(['uploads.root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'nao-existe-'.uniqid()]);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('verificacoes.armazenamento', 'indisponivel');
    }

    public function test_ready_nao_acusa_pasta_privada_que_ainda_nao_foi_criada(): void
    {
        // Instalação nova: `private` nasce no primeiro envio privado.
        $raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uploads-'.uniqid();
        mkdir($raiz.DIRECTORY_SEPARATOR.'public', 0o755, true);
        config(['uploads.root' => $raiz]);

        $this->get('/health/ready')->assertOk();
    }

    public function test_status_mostra_cada_componente_em_linguagem_simples(): void
    {
        $this->get('/sistema/status')
            ->assertOk()
            ->assertSee('Status do sistema')
            ->assertSeeInOrder(['Aplicação', 'Operacional', 'Banco de dados', 'Operacional', 'Armazenamento de arquivos', 'Operacional'])
            ->assertSee('Última verificação');
    }

    public function test_status_diz_indisponivel_quando_o_banco_cai(): void
    {
        $this->derrubarBanco();

        $this->get('/sistema/status')->assertOk()->assertSeeInOrder(['Banco de dados', 'Indisponível']);
    }

    public function test_status_nao_revela_infraestrutura(): void
    {
        // C.8.1: nada de IP, porta, versão, nome de container, caminho ou erro.
        $this->derrubarBanco();
        $html = (string) $this->get('/sistema/status')->getContent();
        // Só o texto que a pessoa lê: o CSS embutido tem números que parecem versão.
        $texto = strip_tags((string) preg_replace('#<style.*?</style>#s', '', $html));

        $proibidos = [
            'endereço IP' => '/\b\d{1,3}(\.\d{1,3}){3}\b/',
            // Porta colada a um endereço; a hora "18:04:07" não é porta.
            'porta' => '/(localhost|[a-z0-9-]+\.[a-z]{2,}|\d{1,3}(\.\d{1,3}){3}):\d{2,5}/i',
            'versão' => '/\b\d+\.\d+\.\d+\b/',
            'caminho Windows' => '/[A-Za-z]:\\\\/',
            'caminho Unix' => '#/(var|home|usr|etc|srv)/#',
            'tecnologia' => '/mysql|laravel|php|docker|container|redis/i',
            'erro' => '/exception|sqlstate|stack|trace/i',
        ];
        foreach ($proibidos as $oQue => $padrao) {
            $this->assertDoesNotMatchRegularExpression($padrao, $texto, "A página de status revela {$oQue}.");
        }
    }
}
