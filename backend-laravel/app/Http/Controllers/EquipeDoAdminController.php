<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Curso;
use App\Models\Usuario;
use App\Rules\StrongPasswordRule;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\EquipeDoAdmin;
use App\Services\GestoresDoCurso;
use App\Services\RetiradosDoAdmin;
use App\Support\SenhaInicial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * "Equipe Pedagógica" do painel administrativo, em Blade (M5, fatia 3).
 *
 * A lista e a busca por nome vêm do painel React; a coordenação agora também
 * cria gestor (já escolhendo os cursos dele), troca os cursos de um gestor depois,
 * retira gestor da equipe e reativa quem foi retirado — pedido do responsável
 * em 26-27/10/2026. Um curso pode ter mais de um gestor e pode ficar sem nenhum
 * (`GestoresDoCurso`). Retirar não pede motivo e não mexe nos vínculos: os cursos
 * continuam ligados ao gestor, e reativá-lo devolve tudo como estava.
 */
final class EquipeDoAdminController extends Controller
{
    public function __construct(
        private readonly EquipeDoAdmin $equipe,
        private readonly RetiradosDoAdmin $retirados,
        private readonly GestoresDoCurso $gestoresDoCurso,
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    public function listar(Request $request): View
    {
        $busca = trim((string) $request->query('busca', ''));

        $cursos = [];
        foreach (Curso::query()->orderBy('titulo')->get(['id', 'titulo']) as $curso) {
            $cursos[] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->titulo];
        }

        return view('admin.equipe', [
            'busca' => $busca,
            'gestores' => $this->equipe->listar($busca),
            'retirados' => $this->retirados->gestores(),
            'total' => $this->equipe->total(),
            'cursosDisponiveis' => $cursos,
            'semGestor' => $this->gestoresDoCurso->cursosSemGestorAtivo(),
            'senhaSugerida' => SenhaInicial::gerar(),
        ]);
    }

    /**
     * Criar o acesso de um gestor. Conta ativa na hora (é a coordenação quem cria), com
     * os cursos marcados já vinculados. Sem senha informada, gera-se uma e ela aparece
     * UMA vez para a coordenação repassar.
     */
    public function criar(Request $request): RedirectResponse
    {
        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $nome = trim((string) preg_replace('/\s{2,}/', ' ', $request->string('nome')->toString()));

        $dados = Validator::make(
            array_merge($request->all(), ['nome' => $nome]),
            [
                'nome' => ['required', 'string', 'max:150', static function (string $atributo, mixed $valor, \Closure $falhou): void {
                    $texto = is_string($valor) ? $valor : '';
                    if (preg_match("/^[\\p{L}'\\- ]+$/u", $texto) !== 1) {
                        $falhou('O nome aceita apenas letras — sem números ou símbolos.');
                    } elseif (count(array_filter(explode(' ', $texto), static fn (string $p): bool => $p !== '')) < 2) {
                        $falhou('Informe o nome completo, com pelo menos um sobrenome.');
                    }
                }],
                'email' => ['required', 'string', 'max:254', 'regex:/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/'],
                'senha' => ['nullable', 'string', 'max:128', new StrongPasswordRule],
                'cursos' => ['nullable', 'array'],
                'cursos.*' => ['string', 'max:191'],
            ],
            ['email.regex' => 'Informe um e-mail válido, no formato nome@dominio.com.'],
            ['nome' => 'nome', 'email' => 'e-mail', 'senha' => 'senha'],
        )->validateWithBag('novoGestor');

        $informada = trim((string) ($dados['senha'] ?? ''));
        $senha = $informada !== '' ? $informada : SenhaInicial::gerar();

        try {
            $criado = $this->auth->register([
                'nome' => $nome,
                'email' => mb_strtolower(trim((string) $dados['email'])),
                'password' => $senha,
                'papel' => 'instructor',
            ], $admin);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()], 'novoGestor');
        }

        $id = (string) $criado['usuario']['id'];
        $cursos = is_array($dados['cursos'] ?? null) ? array_values(array_filter($dados['cursos'], 'is_string')) : [];
        $this->gestoresDoCurso->definirCursosDoGestor($id, $cursos, $admin['sub']);

        $this->audit->log($request, 'Cadastro de Usuário', 'Gestor "'.$nome.'" ('.$id.') cadastrado pelo painel administrativo, com '.count($cursos).' curso(s).');

        $resposta = redirect('/admin/equipe')->with('avisoDaEquipe', $nome.' foi adicionado(a) à equipe.');

        return $informada === '' ? $resposta->with('senhaDefinida', ['nome' => $nome, 'senha' => $senha]) : $resposta;
    }

    /** Trocar os cursos que o gestor gerencia: o que ficou marcado vale, o resto é desvinculado. */
    public function cursos(Request $request, string $gestorId): RedirectResponse
    {
        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');
        $gestor = $this->gestor($gestorId);

        $dados = $request->validate([
            'cursos' => ['nullable', 'array'],
            'cursos.*' => ['string', 'max:191'],
        ]);
        $cursos = is_array($dados['cursos'] ?? null) ? array_values(array_filter($dados['cursos'], 'is_string')) : [];

        $this->gestoresDoCurso->definirCursosDoGestor($gestorId, $cursos, $admin['sub']);
        $this->audit->log($request, 'Alteração de Cursos do Gestor', 'Cursos do gestor '.$gestorId.' redefinidos pela coordenação ('.count($cursos).' marcado(s)).', 'WARNING');

        return redirect('/admin/equipe')->with('avisoDaEquipe', 'Os cursos de '.$gestor->nome.' foram atualizados.');
    }

    /**
     * Retirar o gestor da equipe. Sem motivo obrigatório. A conta é inativada e bloqueada
     * (nada é apagado, ADR 12), as sessões abertas caem, e os cursos continuam ligados a
     * ele — ficam "sem gestor ativo" até a coordenação escolher outro, ou até ele voltar.
     */
    public function retirar(Request $request, string $gestorId): RedirectResponse
    {
        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');
        $gestor = $this->gestor($gestorId);

        $dados = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);
        $motivo = trim((string) ($dados['motivo'] ?? ''));

        $this->auth->deleteUser($gestorId, $admin, $motivo !== '' ? $motivo : null);
        $this->audit->log($request, 'Inativação de Usuário', 'Gestor '.$gestorId.' retirado da equipe (registro e vínculos preservados).');

        return redirect('/admin/equipe')->with(
            'avisoDaEquipe',
            $gestor->nome.' foi retirado(a) da equipe. O cadastro e os cursos ficam guardados e voltam se a pessoa for reativada.',
        );
    }

    /**
     * Devolver à equipe um gestor retirado. A conta volta ativa (a retirada a bloqueou) e as
     * sessões anteriores são encerradas, então a pessoa entra com login novo. As disciplinas
     * que ela gerencia nunca saíram do vínculo (a retirada inativa a conta, não os vínculos),
     * e voltam a aparecer sob a gestão dela.
     */
    public function reativar(Request $request, string $gestorId): RedirectResponse
    {
        $gestor = Usuario::onlyTrashed()->where('papel', 'instructor')->find($gestorId);
        abort_if($gestor === null, 404);

        $this->auth->reactivateUser($gestorId);
        $this->audit->log($request, 'Reativação de Usuário', 'Gestor '.$gestorId.' reativado pela coordenação (conta ativa; sessões anteriores encerradas).', 'WARNING');

        return redirect('/admin/equipe')->with(
            'avisoDaEquipe',
            $gestor->nome.' voltou à equipe. A conta está ativa; a pessoa entra com um login novo.',
        );
    }

    /** O gestor ATIVO da equipe; retirado ou de outro papel é 404. */
    private function gestor(string $gestorId): Usuario
    {
        $gestor = Usuario::query()->where('papel', 'instructor')->find($gestorId);
        abort_if($gestor === null, 404);

        return $gestor;
    }
}
