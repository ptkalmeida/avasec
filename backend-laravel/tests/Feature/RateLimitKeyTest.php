<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Chave dos rate limiters. O AVASEC roda em laboratório de escola e em rede
 * doméstica atrás de CGNAT: dezenas de usuários legítimos saem pelo MESMO IP.
 * Um limitador keyed só por IP transformaria o início de uma aula em bloqueio,
 * então cada balde é por identidade (usuário do token, ou identificador tentado
 * no login), com um teto por IP muito mais alto.
 */
final class RateLimitKeyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** Senha usada por SeedsIdentity::makeStudent ao provisionar a conta. */
    private const SENHA_SEED = 'senha123456';

    protected function setUp(): void
    {
        parent::setUp();
        // Os baldes vivem no cache e vazam entre testes (e entre execuções): limpa os
        // usados aqui, senão a ordem dos testes muda o resultado.
        RateLimiter::clear('login-ip:127.0.0.1');
        RateLimiter::clear('ip:127.0.0.1');
        RateLimiter::clear('ip:203.0.113.10');
    }

    public function test_login_bucket_is_per_identifier_so_one_ip_does_not_block_the_whole_lab(): void
    {
        $vitima = $this->makeStudent('Aluno Laboratorio');
        $cpfEsgotado = $this->makeCpf();

        // Esgota o balde de UM identificador (CPF válido porém não cadastrado, para
        // não disparar o bloqueio por conta, que age na 5ª tentativa): 10 por 15 min.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['cpf' => $cpfEsgotado, 'password' => 'errada'])
                ->assertStatus(401);
        }

        // 11ª tentativa daquele identificador: barrada.
        $this->postJson('/api/auth/login', ['cpf' => $cpfEsgotado, 'password' => 'errada'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');

        // MESMO IP, outro aluno: continua entrando. Era isto que o limite por IP
        // quebrava — o 11º acesso do laboratório derrubava todo mundo.
        $this->postJson('/api/auth/login', [
            'cpf' => $vitima['cpf'],
            'password' => self::SENHA_SEED,
        ])->assertOk()->assertJsonPath('user.id', $vitima['id']);
    }

    public function test_authenticated_bucket_is_per_user_not_per_ip(): void
    {
        $a = $this->makeStudent('Aluno Balde A');
        $b = $this->makeStudent('Aluno Balde B');

        // Troca de senha: 5 por 15 min. Esgota o balde do aluno A com senha atual
        // errada (401), sem tocar na conta do B.
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', "Bearer {$a['token']}")
                ->putJson('/api/auth/password', [
                    'currentPassword' => 'errada',
                    'newPassword' => 'NovaSenha#2026',
                ])->assertStatus(401);
        }

        $this->withHeader('Authorization', "Bearer {$a['token']}")
            ->putJson('/api/auth/password', [
                'currentPassword' => 'errada',
                'newPassword' => 'NovaSenha#2026',
            ])->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');

        // Aluno B, mesmo IP, balde intacto: a senha realmente troca.
        $this->withHeader('Authorization', "Bearer {$b['token']}")
            ->putJson('/api/auth/password', [
                'currentPassword' => self::SENHA_SEED,
                'newPassword' => 'NovaSenha#2026',
            ])->assertOk();
    }

    public function test_public_telemetry_is_rate_limited(): void
    {
        // Rota pública que ESCREVE no banco: sem limite, um anônimo enfileirava
        // linhas com texto próprio até encher a tabela.
        $limite = 60;
        for ($i = 0; $i < $limite; $i++) {
            $this->postJson('/api/telemetry', [
                'action' => 'TESTE_LIMITE',
                'details' => 'evento '.$i,
            ])->assertStatus(201);
        }

        $this->postJson('/api/telemetry', ['action' => 'TESTE_LIMITE', 'details' => 'estouro'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_audited_ip_cannot_be_forged_by_the_client(): void
    {
        // O header cru não é confiável: só um proxy listado em TrustProxies pode
        // definir o IP do cliente. Antes o controller preferia o X-Forwarded-For de
        // qualquer origem, deixando qualquer um assinar o log de auditoria com o IP
        // que quisesse. REMOTE_ADDR aqui é um IP externo (NÃO confiável), então o
        // header precisa ser ignorado — de 127.0.0.1 ele seria honrado de propósito,
        // pois é o proxy reverso local do deploy.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('X-Forwarded-For', '8.8.8.8')
            ->postJson('/api/telemetry', [
                'action' => 'TENTATIVA_DE_FORJA',
                'details' => 'ip deve ser o real, não 8.8.8.8',
            ])->assertStatus(201);

        $admin = $this->staffToken('admin');
        $eventos = $this->withHeader('Authorization', "Bearer $admin")
            ->getJson('/api/telemetry')->assertOk()->json('items');

        $forjado = array_filter(
            is_array($eventos) ? $eventos : [],
            static fn ($e) => ($e['action'] ?? null) === 'TENTATIVA_DE_FORJA'
        );

        $this->assertNotEmpty($forjado, 'O evento deveria ter sido gravado.');
        foreach ($forjado as $evento) {
            $this->assertNotSame('8.8.8.8', $evento['ipAddress'] ?? null);
            $this->assertSame('203.0.113.10', $evento['ipAddress'] ?? null);
        }
    }
}
