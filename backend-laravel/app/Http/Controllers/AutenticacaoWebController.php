<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Middleware\JwtAuthenticate;
use App\Rules\CpfRule;
use App\Rules\StrongPasswordRule;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Support\SessaoJwt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * Entrar e cadastrar-se em telas de verdade, e não num modal do SPA.
 *
 * ## O que muda, e o que deliberadamente não muda
 *
 * **Não muda o mecanismo.** Continua sendo o mesmo JWT, no mesmo cookie
 * `ava_session`, emitido pelo mesmo `AuthService`, com o mesmo bloqueio por
 * tentativas e os mesmos limitadores de taxa. A sessão do Laravel **não**
 * entrou no lugar dele: trocar o mecanismo e mudar a tela ao mesmo tempo
 * tornaria impossível dizer qual dos dois quebrou, e o JWT ainda é o que a API
 * e as telas React restantes entendem.
 *
 * **Muda a porta.** O login era um modal dentro do `App.tsx`, alcançável só
 * depois de carregar o SPA inteiro. Agora é um endereço — `/entrar` — que pode
 * ser o destino de um link, de um favorito e do redirecionamento de uma rota
 * protegida. Era isso que travava a migração da home: enquanto o modal vivesse
 * em `/`, migrar `/` deixava o sistema sem porta de entrada.
 *
 * ## Por que o formulário tem seletor de papel
 *
 * Porque o de hoje tem, e pelo mesmo motivo documentado no `AuthController`: o
 * cartão de "Gestão" chegou a autenticar um aluno homônimo. O papel escolhido
 * vai junto e restringe a busca — não concede nada.
 *
 * O identificador muda com o papel porque é assim que o sistema funciona (ADR
 * 11): aluno entra por **CPF**, gestão e admin por **e-mail**.
 */
final class AutenticacaoWebController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    /** Para onde cada papel vai depois de entrar. */
    private const RAIZ_DO_PAPEL = [
        'student' => '/aluno',
        'instructor' => '/inst',
        'admin' => '/admin',
    ];

    public function mostrarLogin(Request $request): View|RedirectResponse
    {
        // Quem já tem sessão válida não vê a tela de entrar: vai para o painel.
        // Sem isto, voltar no navegador depois de entrar mostra o formulário de
        // novo, e a pessoa acha que a sessão caiu.
        $destino = $this->destinoDeQuemJaEstaLogado($request);

        return $destino ?? view('auth.entrar', ['papel' => $request->query('papel', 'student')]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $dados = Validator::make($request->all(), [
            'papel' => ['required', 'in:student,instructor,admin'],
            'identificador' => ['required', 'string', 'max:254'],
            'senha' => ['required', 'string', 'min:1', 'max:128'],
        ])->validate();

        $papel = (string) $dados['papel'];
        $identificador = trim((string) $dados['identificador']);

        try {
            $resultado = $this->auth->login([
                // Aluno entra por CPF; gestão e admin por e-mail (ADR 11).
                'cpf' => $papel === 'student' ? $identificador : null,
                'email' => $papel === 'student' ? null : mb_strtolower($identificador),
                'name' => null,
                'password' => (string) $dados['senha'],
                'role' => $papel,
            ]);
        } catch (Throwable $erro) {
            $this->audit->log($request, 'Tentativa Fracassada', "Falha de login para o identificador: {$identificador}.", 'FAILED');

            /*
             * A mensagem vem do service — ela distingue "credencial inválida" de
             * "conta bloqueada por tentativas" e de "cadastro em análise", e a
             * pessoa precisa dessa diferença para saber o que fazer. O que não
             * se faz é dizer qual dos dois campos errou.
             *
             * `withInput` sem a senha: devolver a senha digitada ao HTML a
             * colocaria no histórico e no cache do navegador.
             */
            return back()
                ->withInput($request->except('senha'))
                ->withErrors(['entrada' => $erro instanceof ApiException ? $erro->getMessage() : 'Não foi possível entrar. Tente novamente.']);
        }

        $this->audit->log($request, 'Autenticação no Sistema', 'Login efetuado com sucesso.', 'SUCCESS', [
            'name' => $resultado['user']['name'],
            'role' => $resultado['user']['role'],
        ]);

        $papelReal = (string) $resultado['user']['role'];

        return redirect(self::RAIZ_DO_PAPEL[$papelReal] ?? '/')
            ->withCookie(SessaoJwt::cookie($resultado['token']));
    }

    /**
     * Sair, por POST.
     *
     * Nunca por GET: um `<img src="/sair">` numa página qualquer deslogaria
     * quem a abrisse. Com POST, o token CSRF do Laravel é o que garante que
     * quem pediu para sair foi a pessoa, no formulário dela.
     */
    public function sair(Request $request): RedirectResponse
    {
        $this->audit->log($request, 'Encerramento de Sessão', 'Logout efetuado.');

        return redirect('/')->withCookie(SessaoJwt::esquecer());
    }

    public function mostrarCadastro(Request $request): View|RedirectResponse
    {
        return $this->destinoDeQuemJaEstaLogado($request) ?? view('auth.cadastro');
    }

    /**
     * Cadastro público: nasce sempre como **aluno** e **pendente**.
     *
     * Quem decide se a conta entra é a coordenação — por isso não há token aqui
     * e a pessoa não é levada ao painel. Mudar isso seria conceder acesso a
     * quem se cadastrou sozinho, e o service já recusa papel diferente de
     * aluno para quem não é admin.
     */
    public function cadastrar(Request $request): RedirectResponse
    {
        $dados = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'cpf' => ['required', 'string', 'max:20', new CpfRule],
            'password' => ['required', 'string', 'max:128', new StrongPasswordRule],
            'municipio' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'areaInteresse' => ['nullable', 'string', 'max:120'],
        ])->validate();

        try {
            $this->auth->register([
                'name' => (string) $dados['name'],
                'email' => mb_strtolower(trim((string) $dados['email'])),
                'password' => (string) $dados['password'],
                'cpf' => (string) $dados['cpf'],
                'municipio' => $dados['municipio'] ?? null,
                'uf' => $dados['uf'] ?? null,
                'areaInteresse' => $dados['areaInteresse'] ?? null,
            ], null);
        } catch (Throwable $erro) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['entrada' => $erro instanceof ApiException ? $erro->getMessage() : 'Não foi possível concluir o cadastro.']);
        }

        $this->audit->log($request, 'Cadastro de Usuário', 'Cadastro público recebido (aguardando confirmação).');

        return redirect('/entrar')->with(
            'aviso',
            'Cadastro recebido com sucesso. Seu acesso será liberado assim que a coordenação da '
            .'Escola Estadual da Cultura RJ confirmar sua matrícula. Depois da confirmação, entre com seu CPF e senha.',
        );
    }

    /**
     * O painel de quem já tem sessão válida, ou null.
     *
     * Token inválido NÃO é erro aqui: é exatamente quem precisa da tela de
     * entrar. Por isso a exceção é engolida — e só aqui.
     */
    private function destinoDeQuemJaEstaLogado(Request $request): ?RedirectResponse
    {
        try {
            $identidade = JwtAuthenticate::decode(JwtAuthenticate::extractToken($request));
        } catch (Throwable) {
            return null;
        }

        if ($identidade === null) {
            return null;
        }

        $papel = is_string($identidade['role']) ? $identidade['role'] : '';

        return redirect(self::RAIZ_DO_PAPEL[$papel] ?? '/');
    }
}
