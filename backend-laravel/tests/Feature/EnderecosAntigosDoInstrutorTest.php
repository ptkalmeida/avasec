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
 * Só o editor de aula (`/aula/{id}`) continua React, até a M6.
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

    /** As rotas reais NÃO são engolidas pelas que casam com tudo. */
    public function test_as_rotas_reais_continuam_sendo_atendidas(): void
    {
        $t = $this->token();

        $this->como($t)->get('/inst/mensagens')->assertOk();
        $this->como($t)->get('/inst')->assertOk();
    }
}
