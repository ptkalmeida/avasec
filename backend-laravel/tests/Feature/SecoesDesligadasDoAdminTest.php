<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * A virada do painel administrativo (fatia 8 do M5): `/admin` inteiro é do
 * servidor. Dois tipos de endereço sobravam para o React — as seções desligadas
 * por flag e o endereço desconhecido —, e os dois passaram a ser do Laravel.
 *
 * Se a coordenação LIGAR uma das flags, a rota responde 501 e o teste de
 * "desligada" falha dizendo qual tela construir — é de propósito: ligar a função
 * sem a tela é descobrir o problema pelo administrador.
 */
final class SecoesDesligadasDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function secoes(): array
    {
        return [
            'exercícios práticos' => ['/admin/exercicios', 'atividadesPraticasAvancadas', 'Exercícios Práticos'],
            'solicitações acadêmicas' => ['/admin/documentos', 'solicitacoesAcademicas', 'Solicitações Acadêmicas'],
        ];
    }

    #[DataProvider('secoes')]
    public function test_a_secao_desligada_e_do_servidor_e_diz_que_nao_esta_disponivel(string $endereco, string $flag, string $titulo): void
    {
        $this->assertNotTrue(
            config('features.'.$flag),
            "A flag {$flag} foi ligada: construa a tela de {$titulo} em Blade antes de ligar a função.",
        );

        $resposta = $this->como($this->staffToken('admin'))->get($endereco);

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee($titulo);
        $resposta->assertSee('Esta seção não está disponível nesta versão da plataforma.');
        $resposta->assertSee('Voltar ao Dashboard');
        // Era a tela antiga inteira que aparecia por baixo do aviso: nada disso volta.
        $resposta->assertDontSee('Lançar Nova Atividade');
        $resposta->assertDontSee('Excluir');
    }

    #[DataProvider('secoes')]
    public function test_ligada_a_flag_a_rota_responde_501_apontando_a_tela_a_construir(string $endereco, string $flag, string $titulo): void
    {
        config(['features.'.$flag => true]);

        $this->como($this->staffToken('admin'))->get($endereco)->assertStatus(501);
        $this->assertNotSame('', $titulo);
    }

    #[DataProvider('secoes')]
    public function test_so_o_admin_chega_a_secao_desligada(string $endereco): void
    {
        $this->get($endereco)->assertRedirect('/entrar');

        $aluno = $this->makeStudent('Aluno Secao Desligada Adm');
        $this->como($aluno['token'])->get($endereco)->assertRedirect('/aluno');
    }

    public function test_endereco_desconhecido_sob_admin_volta_ao_dashboard_em_vez_de_cair_no_react(): void
    {
        $token = $this->staffToken('admin');

        foreach (['/admin/qualquer-coisa', '/admin/alunos/x/y/z', '/admin/cursos/x', '/admin/relatorio', '/admin/perfil/outra'] as $endereco) {
            $this->como($token)->get($endereco)->assertRedirect('/admin');
        }
    }

    public function test_o_aluno_que_digita_admin_vai_para_a_area_dele_e_o_visitante_para_o_login(): void
    {
        $this->get('/admin/qualquer-coisa')->assertRedirect('/entrar');
        $this->get('/admin')->assertRedirect('/entrar');

        $aluno = $this->makeStudent('Aluno Digita Admin');
        $this->como($aluno['token'])->get('/admin/qualquer-coisa')->assertRedirect('/aluno');
    }

    public function test_o_menu_nao_oferece_as_secoes_desligadas(): void
    {
        $tela = $this->como($this->staffToken('admin'))->get('/admin');

        $tela->assertDontSee('Exercícios Práticos');
        $tela->assertDontSee('Solicitações Acadêmicas');
    }
}
