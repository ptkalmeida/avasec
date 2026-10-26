<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Os endereços do painel antigo que sobraram, e que não podem cair no React.
 *
 * Sem estas rotas, `/inst/curso/x/mensagens` e qualquer endereço desconhecido
 * caíam no SPA, que tratava seção desconhecida como "Gestão do Curso" e
 * desenhava o painel ANTIGO — e dali o instrutor navegava, por dentro do React,
 * às versões velhas das telas já migradas.
 *
 * O editor de aula foi para `/aulas/{id}` (fase M6): os endereços `/aula/...` do
 * painel React, que circulam em favoritos e em links já enviados, levam ao novo.
 * E como `/inst` inteiro é do Laravel, NADA sob ele pode sobrar para o fallback,
 * que serve o React — que o devolveria ao servidor, para sempre.
 */
final class EnderecosAntigosDoInstrutorTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function token(): string
    {
        $id = (string) Str::uuid();
        User::query()->create([
            'id' => $id,
            'name' => 'Gestor Enderecos',
            'email' => Str::lower(Str::random(10)).'@example.com',
            'passwordHash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'role' => 'instructor',
        ]);

        return Jwt::issue($id, 'Gestor Enderecos', 'instructor');
    }

    public function test_visitante_vai_para_o_login(): void
    {
        foreach (['/inst/curso/x/mensagens', '/inst/curso/x/qualquer', '/inst/qualquer'] as $caminho) {
            $this->get($caminho)->assertRedirect('/entrar');
        }
    }

    public function test_a_caixa_de_mensagens_por_curso_leva_a_caixa_unica(): void
    {
        $this->como($this->token())->get('/inst/curso/qualquer-curso/mensagens')->assertRedirect('/inst/mensagens');
    }

    public function test_secao_desconhecida_do_curso_volta_para_o_curso(): void
    {
        $this->como($this->token())->get('/inst/curso/meu-curso/secao-que-nao-existe')
            ->assertRedirect('/inst/curso/meu-curso');
    }

    public function test_endereco_desconhecido_volta_para_a_escolha(): void
    {
        $t = $this->token();

        $this->como($t)->get('/inst/qualquer-coisa')->assertRedirect('/inst');
        // `/inst/curso` sem curso nenhum também.
        $this->como($t)->get('/inst/curso')->assertRedirect('/inst');
    }

    public function test_os_enderecos_antigos_do_editor_levam_aos_novos(): void
    {
        $t = $this->token();

        $this->como($t)->get('/inst/curso/meu-curso/aula/nova')->assertRedirect('/inst/curso/meu-curso/aulas/nova');
        $this->como($t)->get('/inst/curso/meu-curso/aula/lesson-123')->assertRedirect('/inst/curso/meu-curso/aulas/lesson-123');
        // O curso e a aula são levados como estão, inclusive o que precisa de escape.
        $this->como($t)->get('/inst/curso/meu curso/aula/a b')->assertRedirect('/inst/curso/meu%20curso/aulas/a%20b');
    }

    public function test_visitante_nos_enderecos_antigos_do_editor_vai_para_o_login(): void
    {
        $this->get('/inst/curso/x/aula/nova')->assertRedirect('/entrar');
        $this->get('/inst/curso/x/aula/y')->assertRedirect('/entrar');
    }

    /** Qualquer profundidade: o que o Laravel não atende não pode cair no React. */
    public function test_endereco_desconhecido_em_qualquer_profundidade_volta_para_a_escolha(): void
    {
        // Visitante primeiro: o cookie de `como()` fica no cliente de teste.
        $this->get('/inst/a/b/c')->assertRedirect('/entrar');

        $t = $this->token();

        foreach (['/inst/a/b', '/inst/a/b/c/d', '/inst/curso/x/aulas/y/inexistente', '/inst/curso/x/aulas/y/anexos/z'] as $caminho) {
            $this->como($t)->get($caminho)->assertRedirect();
            $this->assertStringStartsWith('/inst', (string) parse_url((string) $this->como($t)->get($caminho)->headers->get('Location'), PHP_URL_PATH));
        }
    }

    /** As rotas reais NÃO são engolidas pelas que casam com tudo. */
    public function test_as_rotas_reais_continuam_sendo_atendidas(): void
    {
        $t = $this->token();

        $this->como($t)->get('/inst/mensagens')->assertOk();
        $this->como($t)->get('/inst')->assertOk();
    }
}
