<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * As seções da área do aluno que existem no sistema mas estão DESLIGADAS.
 *
 * Decisão do responsável em 20/10: o servidor atende esses endereços e diz que
 * a função está indisponível; a tela é construída quando a função for ligada.
 *
 * O teste que mais importa é o último: nenhum endereço de `/aluno` pode cair no
 * React. Enquanto um sobrasse, bastava chegar nele para navegar, por dentro do
 * SPA, até as versões antigas de todas as telas já migradas.
 */
final class SecoesDesligadasDoAlunoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** Seção => flag que a liga. */
    private const SECOES = [
        '/aluno/eventos' => 'eventosWebinars',
        '/aluno/solicitacoes' => 'solicitacoesAcademicas',
        '/aluno/mensagens' => 'forum',
        '/aluno/curso/qualquer/exercicios' => 'atividadesPraticasAvancadas',
        '/aluno/curso/qualquer/ao-vivo/sessao-1' => 'liveClassroom',
    ];

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        foreach (array_keys(self::SECOES) as $caminho) {
            $this->get($caminho)->assertRedirect('/entrar', "Seção aberta a visitante: {$caminho}");
        }
    }

    public function test_cada_secao_desligada_explica_e_oferece_a_volta(): void
    {
        $aluno = $this->makeStudent('Aluno Secoes Desligadas');

        foreach (array_keys(self::SECOES) as $caminho) {
            $resposta = $this->como($aluno['token'])->get($caminho);

            $resposta->assertOk();
            $resposta->assertDontSee('<div id="root">', false);
            $resposta->assertSee('Esta seção não está disponível.', escape: false);
            $resposta->assertSee('href="/aluno"', escape: false);
        }
    }

    /**
     * A guarda que avisa quem ligar a função: dizer "indisponível" sobre algo
     * que a escola ligou seria mentira, e a tela ainda não existe.
     */
    public function test_ligar_a_flag_deixa_de_responder_o_aviso(): void
    {
        $aluno = $this->makeStudent('Aluno Secoes Ligadas');

        foreach (self::SECOES as $caminho => $flag) {
            config(['features.'.$flag => true]);

            $this->como($aluno['token'])->get($caminho)
                ->assertStatus(501, "A seção {$caminho} precisa de tela em Blade agora que `{$flag}` está ligada.");

            config(['features.'.$flag => false]);
        }
    }

    /** Endereço desconhecido sob `/aluno` volta ao painel, e não ao React. */
    public function test_endereco_desconhecido_do_aluno_volta_ao_painel(): void
    {
        $aluno = $this->makeStudent('Aluno Endereco Desconhecido');

        foreach (['/aluno/inventado', '/aluno/curso/x/coisa-que-nao-existe', '/aluno/a/b/c'] as $caminho) {
            $this->como($aluno['token'])->get($caminho)->assertRedirect('/aluno');
        }
    }

    /**
     * O contrato de rotas cobre `/aluno` inteiro por prefixo. Se alguém voltar
     * a listar telas de `/aluno` uma a uma em "exatos", o que ficar de fora
     * cai no React de novo — que é como o defeito nasceu.
     */
    public function test_o_contrato_entrega_a_area_do_aluno_inteira_ao_servidor(): void
    {
        /** @var array{prefixos: list<string>, exatos?: list<string>} $contrato */
        $contrato = json_decode((string) file_get_contents(base_path('../rotas-laravel.json')), true);

        $this->assertContains('/aluno', $contrato['prefixos'], 'A área do aluno saiu dos prefixos do contrato.');

        foreach ($contrato['exatos'] ?? [] as $uri) {
            $this->assertStringStartsNotWith('/aluno', $uri, "`{$uri}` virou exato: o resto de /aluno voltaria ao React.");
        }
    }
}
