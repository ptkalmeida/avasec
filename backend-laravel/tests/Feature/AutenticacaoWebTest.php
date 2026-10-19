<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Entrar, cadastrar-se e sair em Blade (fase M2).
 *
 * É a parte mais sensível de toda a migração: a porta mudou de lugar, e o que
 * não pode mudar é **quem passa por ela**. Por isso o arquivo confere, em
 * ordem: que a credencial certa entra, que a errada não entra, que a resposta
 * não conta qual campo errou, que o cookie emitido é o mesmo da API, e que sair
 * exige POST com token CSRF.
 *
 * `DatabaseTransactions`: os usuários criados aqui desaparecem ao fim de cada
 * teste. Nada é apagado de verdade (ADR 12) — a transação é desfeita.
 */
final class AutenticacaoWebTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    /** A senha que o `SeedsIdentity` usa ao criar a conta. */
    private const SENHA = 'senha123456';

    public function test_a_tela_de_entrar_responde_em_blade(): void
    {
        $resposta = $this->get('/entrar');

        $resposta->assertOk();
        $resposta->assertSee('Entrar no AVASEC', escape: false);
        // Formulário que escreve precisa de token CSRF; sem ele, todo envio é 419.
        $resposta->assertSee('name="_token"', escape: false);
        $resposta->assertDontSee('<div id="root">', escape: false);
    }

    public function test_aluno_entra_por_cpf_e_recebe_a_sessao(): void
    {
        $aluno = $this->makeStudent('Aluno Login Blade');

        $resposta = $this->post('/entrar', [
            'papel' => 'student',
            'identificador' => $aluno['cpf'],
            'senha' => self::SENHA,
        ]);

        $resposta->assertRedirect('/aluno');

        // O cookie tem de ser o MESMO que a API emite: é ele que as telas React
        // ainda vivas e o middleware da área do aluno leem.
        $cookie = $resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false);
        $this->assertNotNull($cookie, 'O login não emitiu o cookie de sessão.');
        $this->assertTrue($cookie->isHttpOnly(), 'O cookie de sessão precisa ser HttpOnly.');
        $this->assertSame('lax', $cookie->getSameSite());

        $identidade = JwtAuthenticate::decode($cookie->getValue());
        $this->assertSame($aluno['id'], $identidade['sub']);
    }

    public function test_a_sessao_emitida_pelo_formulario_abre_a_area_do_aluno(): void
    {
        $aluno = $this->makeStudent('Aluno Sessao Blade');

        $cookie = $this->post('/entrar', [
            'papel' => 'student',
            'identificador' => $aluno['cpf'],
            'senha' => self::SENHA,
        ])->getCookie(JwtAuthenticate::SESSION_COOKIE, false);

        $this->assertNotNull($cookie);

        // A prova de ponta a ponta: a credencial digitada na tela leva a uma
        // tela autenticada de verdade, e não só a um redirecionamento bonito.
        $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $cookie->getValue())
            ->get('/aluno')
            ->assertOk()
            ->assertSee('Olá, '.$aluno['name'], escape: false);
    }

    public function test_senha_errada_nao_entra_e_nao_diz_qual_campo_errou(): void
    {
        $aluno = $this->makeStudent('Aluno Senha Errada');

        $resposta = $this->from('/entrar')->post('/entrar', [
            'papel' => 'student',
            'identificador' => $aluno['cpf'],
            'senha' => 'senha-que-nao-e-a-dele',
        ]);

        $resposta->assertRedirect('/entrar');
        $resposta->assertSessionHasErrors('entrada');
        $this->assertNull(
            $resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false),
            'Login recusado não pode emitir sessão.',
        );

        // A mensagem não pode dizer que o CPF existe e a senha é que falhou —
        // isso transforma a tela num confirmador de quem tem cadastro.
        $erro = (string) session('errors')?->first('entrada');
        $this->assertStringNotContainsStringIgnoringCase('senha incorreta', $erro);
        $this->assertStringNotContainsStringIgnoringCase('usuário não encontrado', $erro);
    }

    public function test_a_senha_digitada_nao_volta_no_formulario(): void
    {
        $aluno = $this->makeStudent('Aluno Senha Nao Volta');

        $this->from('/entrar')->post('/entrar', [
            'papel' => 'student',
            'identificador' => $aluno['cpf'],
            'senha' => 'uma-senha-qualquer-errada',
        ]);

        // `withInput` devolve o que foi digitado para a pessoa não redigitar —
        // mas a senha no HTML entraria no histórico e no cache do navegador.
        $this->assertNull(session('_old_input.senha'));
        $this->assertSame($aluno['cpf'], session('_old_input.identificador'));
    }

    /**
     * O cartão de "Gestão" chegou a autenticar um aluno homônimo no React. O
     * papel escolhido restringe a busca — e continua restringindo aqui.
     */
    public function test_papel_escolhido_restringe_a_busca(): void
    {
        $aluno = $this->makeStudent('Aluno Papel Errado');

        $resposta = $this->from('/entrar')->post('/entrar', [
            'papel' => 'admin',
            'identificador' => $aluno['cpf'],
            'senha' => self::SENHA,
        ]);

        $resposta->assertSessionHasErrors('entrada');
        $this->assertNull($resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false));
    }

    public function test_quem_ja_esta_logado_vai_para_o_painel_em_vez_da_tela_de_entrar(): void
    {
        $aluno = $this->makeStudent('Aluno Ja Logado');

        $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, Jwt::issue($aluno['id'], $aluno['name'], 'student'))
            ->get('/entrar')
            ->assertRedirect('/aluno');
    }

    public function test_sair_e_post_com_csrf_e_apaga_a_sessao(): void
    {
        $aluno = $this->makeStudent('Aluno Saindo');
        $token = Jwt::issue($aluno['id'], $aluno['name'], 'student');

        $resposta = $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token)->post('/sair');

        $resposta->assertRedirect('/');

        // `forget` emite o cookie com valor vazio e validade no passado.
        $cookie = $resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false);
        $this->assertNotNull($cookie);
        $this->assertSame('', (string) $cookie->getValue());
    }

    /**
     * Sair por GET deslogaria quem abrisse uma página com `<img src="/sair">`.
     *
     * O GET existe (senão o caminho cairia no fallback e devolveria o casco do
     * React com 200, que é a armadilha registrada na M0), mas ele NÃO encerra
     * sessão: só manda para a página inicial.
     */
    public function test_sair_por_get_nao_encerra_a_sessao(): void
    {
        $aluno = $this->makeStudent('Aluno Sair Por Get');
        $token = Jwt::issue($aluno['id'], $aluno['name'], 'student');

        $resposta = $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token)->get('/sair');

        $resposta->assertRedirect('/');
        $this->assertNull(
            $resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false),
            'GET /sair não pode mexer no cookie de sessão.',
        );

        // E a sessão continua valendo: a área do aluno segue aberta.
        $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token)->get('/aluno')->assertOk();
    }

    public function test_cadastro_publico_nasce_pendente_e_nao_entra_sozinho(): void
    {
        $cpf = $this->makeCpf();
        $email = 'cadastro-blade-'.uniqid().'@example.com';

        $resposta = $this->post('/cadastro', [
            'name' => 'Pessoa Cadastro Blade',
            'email' => $email,
            'cpf' => $cpf,
            'password' => 'SenhaForte123',
        ]);

        $resposta->assertRedirect('/entrar');
        $resposta->assertSessionHas('aviso');

        // Cadastro público não concede acesso: quem libera é a coordenação.
        $this->assertNull($resposta->getCookie(JwtAuthenticate::SESSION_COOKIE, false));

        $criado = User::query()->where('email', $email)->first();
        $this->assertNotNull($criado);
        $this->assertSame('pending_confirmation', $criado->status);
        $this->assertSame('student', $criado->role);
    }

    /**
     * O cadastro público não pode escolher o próprio papel — seria promoção a
     * admin por campo escondido no formulário.
     */
    public function test_cadastro_publico_nao_consegue_pedir_papel_de_admin(): void
    {
        $email = 'escalada-'.uniqid().'@example.com';

        $this->post('/cadastro', [
            'name' => 'Tentativa De Escalada',
            'email' => $email,
            'cpf' => $this->makeCpf(),
            'password' => 'SenhaForte123',
            'role' => 'admin',
        ]);

        $criado = User::query()->where('email', $email)->first();
        $this->assertNotNull($criado);
        $this->assertSame('student', $criado->role);
    }
}
