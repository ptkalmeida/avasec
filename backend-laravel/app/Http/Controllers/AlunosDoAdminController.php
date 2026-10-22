<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Course;
use App\Models\User;
use App\Rules\CpfRule;
use App\Rules\StrongPasswordRule;
use App\Services\AlunosDoAdmin;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\EnrollmentService;
use App\Support\SenhaInicial;
use App\Support\VigenciaDoCurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * "Alunos" do painel administrativo, em Blade (M5, fatia 5).
 *
 * A lista e a ficha só leem; as ações (cadastrar, redefinir senha, matrícula
 * múltipla, inativar) passam pelos mesmos services da API, com a mesma
 * auditoria. O que o painel React simulava no navegador e o banco não sabe —
 * RA, turma, último acesso, risco de evasão, status e pendências "editáveis" —
 * não veio: ver `AlunosDoAdmin`.
 */
final class AlunosDoAdminController extends Controller
{
    /** As 27 unidades da federação: a UF só pode ser uma destas. */
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function __construct(
        private readonly AlunosDoAdmin $alunos,
        private readonly AuthService $auth,
        private readonly EnrollmentService $matriculas,
        private readonly AuditLogger $audit,
    ) {}

    public function listar(Request $request): View|RedirectResponse
    {
        $filtros = [
            'busca' => trim((string) $request->query('busca', '')),
            'curso' => trim((string) $request->query('curso', '')),
            'matricula' => (string) $request->query('matricula', ''),
            'conta' => (string) $request->query('conta', ''),
            'ordem' => (string) $request->query('ordem', 'nome'),
        ];

        $pagina = max(1, (int) $request->query('pagina', '1'));
        $resultado = $this->alunos->listar($filtros, $pagina);

        // A lista encolhe (filtro, inativação): página além do fim volta para a última.
        if ($pagina > $resultado['paginas']) {
            return redirect('/admin/alunos?'.http_build_query(array_filter($filtros, static fn (string $v): bool => $v !== '') + ['pagina' => $resultado['paginas']]));
        }

        return view('admin.alunos', [
            'filtros' => $filtros,
            'resultado' => $resultado,
            'pagina' => $pagina,
            'resumo' => $this->alunos->resumo(),
            'cursosDoFiltro' => $this->alunos->cursosParaFiltro(),
            'cursosParaMatricula' => $this->cursosAbertosParaMatricula(),
        ]);
    }

    public function ficha(string $alunoId): View
    {
        $ficha = $this->alunos->ficha($alunoId);
        abort_if($ficha === null, 404);

        return view('admin.aluno', [
            'aluno' => $ficha,
            'senhaSugerida' => SenhaInicial::gerar(),
            'permiteMatriculaMultipla' => config('features.matriculasMultiplas') === true,
            'regraDaSenha' => 'Pelo menos '.StrongPasswordRule::MIN_LENGTH.' caracteres, com ao menos uma letra e um número.',
        ]);
    }

    /**
     * Matricular um novo aluno: cria a conta e a matrícula no curso escolhido.
     *
     * As regras de cada campo são as do formulário do painel React ("cada campo
     * aceita só o que promete"): o nome pede sobrenome e não aceita dígito, o
     * CPF é o login do aluno, a UF é uma sigla de verdade. Conta e matrícula
     * nascem na MESMA transação — conta sem matrícula, por falha no meio, era o
     * defeito silencioso do painel antigo.
     *
     * Município, UF e área em branco ficam em branco. O painel React preenchia
     * "São Paulo", "SP" e "Tecnologia" no lugar, e esses valores inventados
     * passavam a ser o dado do aluno.
     */
    public function cadastrar(Request $request): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $nome = trim((string) preg_replace('/\s{2,}/', ' ', $request->string('name')->toString()));

        $dados = Validator::make(
            // A sigla da UF é maiúscula: o campo da tela a força, e o servidor também.
            array_merge($request->all(), ['name' => $nome, 'uf' => mb_strtoupper(trim($request->string('uf')->toString()))]),
            [
                'name' => ['required', 'string', 'max:150', static function (string $atributo, mixed $valor, \Closure $falhou): void {
                    $texto = is_string($valor) ? $valor : '';
                    if (preg_match("/^[\\p{L}'\\- ]+$/u", $texto) !== 1) {
                        $falhou('O nome aceita apenas letras — sem números ou símbolos.');
                    } elseif (mb_strlen((string) preg_replace("/[\\s'\\-]/u", '', $texto)) < 3) {
                        $falhou('O nome está curto demais.');
                    } elseif (count(array_filter(explode(' ', $texto), static fn (string $p): bool => $p !== '')) < 2) {
                        $falhou('Informe o nome completo, com pelo menos um sobrenome.');
                    }
                }],
                'email' => ['required', 'max:254', static function (string $atributo, mixed $valor, \Closure $falhou): void {
                    $texto = is_string($valor) ? trim($valor) : '';
                    if (preg_match('/^[\d.\-\s]+$/', $texto) === 1 && strlen((string) preg_replace('/\D/', '', $texto)) === 11) {
                        $falhou('Isto é um CPF. O CPF vai no campo próprio — aqui vai o e-mail.');
                    } elseif (preg_match('/\s/', $texto) === 1) {
                        $falhou('O e-mail não pode conter espaços.');
                    } elseif (preg_match('/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/', $texto) !== 1) {
                        $falhou('Informe um e-mail válido, no formato nome@dominio.com.');
                    }
                }],
                'cpf' => ['required', 'string', 'max:20', new CpfRule],
                'curso' => ['required', 'string', 'max:191'],
                'senha' => ['nullable', 'string', 'max:128', new StrongPasswordRule],
                'municipio' => ['nullable', 'string', 'max:120', "regex:/^[\\p{L}'\\- ]+\$/u"],
                'uf' => ['nullable', 'string', 'size:2', 'in:'.implode(',', self::UFS)],
                'areaInteresse' => ['nullable', 'string', 'max:120', 'regex:/^[\\p{L}0-9&,\\-\\/ ]+$/u'],
            ],
            [
                'curso.required' => 'Selecione o curso de matrícula.',
                'municipio.regex' => 'O município aceita apenas letras.',
                'uf.in' => 'UF inválida — use a sigla de duas letras do estado (ex.: PE).',
                'uf.size' => 'UF inválida — use a sigla de duas letras do estado (ex.: PE).',
                'areaInteresse.regex' => 'A área de interesse aceita apenas letras, números e os sinais & , - /',
            ],
            [
                'name' => 'nome',
                'email' => 'e-mail',
                'cpf' => 'CPF',
                'curso' => 'curso',
                'senha' => 'senha',
                'municipio' => 'município',
                'uf' => 'UF',
                'areaInteresse' => 'área de interesse',
            ],
        )->validate();

        $curso = Course::query()->find((string) $dados['curso']);
        if ($curso === null) {
            return $this->voltarComErro($request, 'curso', 'Este curso não existe mais.');
        }
        if (VigenciaDoCurso::vencida(['vigenciaAte' => $curso->getAttribute('vigenciaAte'), 'contractExpirationDate' => $curso->contractExpirationDate])) {
            return $this->voltarComErro($request, 'curso', 'Este curso está com a vigência vencida e não aceita matrícula nova.');
        }

        $informada = trim((string) ($dados['senha'] ?? ''));
        $senha = $informada !== '' ? $informada : SenhaInicial::gerar();

        try {
            $id = DB::transaction(function () use ($dados, $nome, $senha, $admin, $curso): string {
                $criado = $this->auth->register([
                    'name' => $nome,
                    'email' => mb_strtolower(trim((string) $dados['email'])),
                    'password' => $senha,
                    'role' => 'student',
                    'cpf' => (string) $dados['cpf'],
                    'municipio' => $this->opcional($dados['municipio'] ?? null),
                    'uf' => $this->opcional($dados['uf'] ?? null),
                    'areaInteresse' => $this->opcional($dados['areaInteresse'] ?? null),
                ], $admin);

                $userId = (string) $criado['user']['id'];
                $this->matriculas->createAdmission(['userId' => $userId, 'courseId' => (string) $curso->id], $admin);

                return $userId;
            });
        } catch (ApiException $e) {
            return $this->voltarComErro($request, 'entrada', $e->getMessage());
        } catch (Throwable) {
            return $this->voltarComErro($request, 'entrada', 'Não foi possível matricular o aluno.');
        }

        $this->audit->log($request, 'Cadastro de Usuário', 'Aluno "'.$nome.'" cadastrado e matriculado em "'.$curso->title.'" pelo painel administrativo.');

        $resposta = redirect('/admin/alunos/'.$id)->with(
            'avisoDoAluno',
            'Aluno '.$nome.' matriculado(a) em '.$curso->title.', sob supervisão de '.($curso->instructorName ?? 'gestor a definir').'.',
        );

        // A senha gerada vai UMA vez, para a coordenação repassar ao aluno: o
        // toast do painel antigo sumia antes de ela conseguir copiar.
        return $informada === '' ? $resposta->with('senhaDefinida', ['nome' => $nome, 'senha' => $senha]) : $resposta;
    }

    public function redefinirSenha(Request $request, string $alunoId): RedirectResponse
    {
        $aluno = $this->aluno($alunoId);

        $dados = $request->validate([
            'novaSenha' => ['required', 'string', 'max:128', new StrongPasswordRule],
        ], [], ['novaSenha' => 'nova senha']);

        $this->auth->adminResetPassword($alunoId, (string) $dados['novaSenha']);
        $this->audit->log(
            $request,
            'Redefinição de Senha',
            'Senha de "'.$aluno->name.'" ('.$alunoId.') redefinida pela coordenação.',
            'WARNING',
        );

        return redirect('/admin/alunos/'.$alunoId)
            ->with('avisoDoAluno', 'A senha de '.$aluno->name.' foi atualizada.')
            ->with('senhaDefinida', ['nome' => (string) $aluno->name, 'senha' => (string) $dados['novaSenha']]);
    }

    public function matriculaMultipla(Request $request, string $alunoId): RedirectResponse
    {
        abort_unless(config('features.matriculasMultiplas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');
        $aluno = $this->aluno($alunoId);
        $permitir = $request->boolean('permitir');

        $this->matriculas->upsertEnrollment($alunoId, ['canMultiEnroll' => $permitir], $admin);
        $this->audit->log($request, 'Alteração de Matrícula', 'Matrícula de "'.$aluno->name.'" atualizada: matrícula múltipla '.($permitir ? 'permitida' : 'revogada').'.');

        return redirect('/admin/alunos/'.$alunoId)->with(
            'avisoDoAluno',
            $permitir
                ? $aluno->name.' agora pode cursar mais de uma disciplina ao mesmo tempo.'
                : 'Matrícula múltipla revogada para '.$aluno->name.'.',
        );
    }

    /**
     * Retirar o aluno. Inativa a conta — nunca apaga (ADR 12): matrículas, notas,
     * entregas e certificados ficam preservados, e a conta deixa de entrar.
     * O botão do painel React chamava isto de "excluir".
     */
    public function inativar(Request $request, string $alunoId): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');
        $aluno = $this->aluno($alunoId);

        $dados = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);
        $motivo = trim((string) ($dados['motivo'] ?? ''));

        $this->auth->deleteUser($alunoId, $admin, $motivo !== '' ? $motivo : null);
        $this->audit->log($request, 'Inativação de Usuário', 'Usuário '.$alunoId.' inativado (registro preservado).');

        return redirect('/admin/alunos')->with(
            'avisoDosAlunos',
            $aluno->name.' foi retirado(a) da plataforma. O registro, as matrículas e o histórico ficam preservados.',
        );
    }

    private function aluno(string $alunoId): User
    {
        $aluno = User::query()->where('role', 'student')->find($alunoId);
        abort_if($aluno === null, 404);

        return $aluno;
    }

    /**
     * Cursos em que se pode matricular agora: com a vigência em dia, porque o
     * serviço recusa matrícula nova em curso vencido.
     *
     * @return list<array{id:string,titulo:string,gestor:?string}>
     */
    private function cursosAbertosParaMatricula(): array
    {
        $lista = [];
        foreach (Course::query()->orderBy('title')->get() as $curso) {
            if (VigenciaDoCurso::vencida(['vigenciaAte' => $curso->getAttribute('vigenciaAte'), 'contractExpirationDate' => $curso->contractExpirationDate])) {
                continue;
            }
            $lista[] = [
                'id' => (string) $curso->id,
                'titulo' => (string) $curso->title,
                'gestor' => is_string($curso->instructorName) && $curso->instructorName !== '' ? $curso->instructorName : null,
            ];
        }

        return $lista;
    }

    private function opcional(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }

    private function voltarComErro(Request $request, string $campo, string $mensagem): RedirectResponse
    {
        return back()->withInput($request->except(['senha']))->withErrors([$campo => $mensagem]);
    }
}
