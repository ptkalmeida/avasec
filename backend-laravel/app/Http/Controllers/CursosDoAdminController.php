<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Curso;
use App\Models\Usuario;
use App\Services\AuditLogger;
use App\Services\CourseService;
use App\Services\CursosDoAdmin;
use App\Services\RetiradosDoAdmin;
use App\Services\UploadService;
use App\Support\Fuso;
use App\Support\VigenciaDoCurso;
use App\Support\Visibilidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Cursos & Trilhas" do painel administrativo, em Blade (M5, fatia 4).
 *
 * Toda escrita passa pelo `CourseService`, o mesmo da API: ele reconfere o
 * papel, deriva o slug do título e valida o responsável. A auditoria é a mesma
 * linha que o `CourseController` escreve — sem ela, a alteração feita por esta
 * tela sumiria do registro que a feita pela API deixa.
 *
 * ## "Excluir" virou "Retirar do ar"
 *
 * O botão do painel React dizia "Excluir", mas a rota nunca apagou nada (ADR 12):
 * ela inativa a disciplina, e o texto da confirmação já explicava isso. O rótulo
 * agora diz o que acontece.
 */
final class CursosDoAdminController extends Controller
{
    /**
     * As imagens que o painel React sorteava para o curso novo. Públicas porque a área do
     * gestor (`AreaDoInstrutorController::criarCurso`) cria curso com as mesmas.
     */
    public const MINIATURAS = [
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=60',
        'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=800&auto=format&fit=crop&q=60',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&auto=format&fit=crop&q=60',
        'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=800&auto=format&fit=crop&q=60',
    ];

    private const DESCRICAO_PADRAO = 'Este é um curso recém-provido pelo suporte administrativo da instituição.';

    public function __construct(
        private readonly CourseService $cursos,
        private readonly CursosDoAdmin $leitura,
        private readonly RetiradosDoAdmin $retirados,
        private readonly UploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    public function listar(Request $request): View
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $busca = trim((string) $request->query('busca', ''));
        $lista = $this->leitura->listar($busca);

        return view('admin.cursos', [
            'busca' => $busca,
            'cursos' => $lista,
            'retirados' => $this->retirados->cursos(),
            'totalNoCatalogo' => Curso::query()->count(),
            'categorias' => $this->leitura->categorias(),
            'instrutores' => $this->leitura->instrutores(),
            'abrir' => (string) $request->query('curso', ''),
        ]);
    }

    public function criar(Request $request): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'min:3', 'max:200'],
            'categoria' => ['required', 'string', 'min:1', 'max:120'],
            'instrutor_id' => ['nullable', 'string', 'max:191'],
            'descricao' => ['nullable', 'string', 'min:10', 'max:5000'],
            'vigencia' => ['nullable', 'date_format:Y-m-d'],
            'tipo_curso' => ['required', 'in:fixo,ao_vivo'],
        ], [
            'vigencia.date_format' => 'Data de vigência inválida.',
        ], [
            'titulo' => 'título',
            'categoria' => 'área',
            'descricao' => 'ementa',
        ]);

        $titulo = trim((string) $dados['titulo']);
        $descricao = trim((string) ($dados['descricao'] ?? ''));
        $vigencia = trim((string) ($dados['vigencia'] ?? ''));

        $entrada = [
            'titulo' => $titulo,
            'descricao' => $descricao !== '' ? $descricao : self::DESCRICAO_PADRAO,
            'categoria' => trim((string) $dados['categoria']),
            'miniatura' => self::MINIATURAS[crc32($titulo) % count(self::MINIATURAS)],
            'tipo_curso' => $dados['tipo_curso'],
            'tem_chat' => $request->boolean('tem_chat'),
        ];
        if ($vigencia !== '') {
            $entrada['vencimento_contrato_texto'] = $vigencia;
        }
        if (is_string($dados['instrutor_id'] ?? null) && $dados['instrutor_id'] !== '') {
            $entrada['instrutor_id'] = $dados['instrutor_id'];
        }

        try {
            $curso = $this->cursos->createCourse($entrada, $admin);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['instrutor_id' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Criação de Curso', 'Curso "'.$titulo.'" criado pelo painel administrativo.');

        $id = is_string($curso['id'] ?? null) ? $curso['id'] : '';

        return redirect('/admin/cursos?curso='.rawurlencode($id).'#curso-'.$id)
            ->with('avisoDosCursos', 'Curso "'.$titulo.'" provido no catálogo.');
    }

    public function trocarInstrutor(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $dados = $request->validate([
            'instrutor_id' => ['required', 'string', 'max:191'],
        ], ['instrutor_id.required' => 'Escolha o professor responsável.']);

        $atual = $this->curso($cursoId);

        try {
            $atualizado = $this->cursos->updateCourse($cursoId, ['instrutor_id' => $dados['instrutor_id']], $this->admin($request));
        } catch (ApiException $e) {
            return $this->voltar($cursoId)->withErrors(['instrutor_id' => $e->getMessage()]);
        }

        $nome = is_string($atualizado['nome_instrutor'] ?? null) ? $atualizado['nome_instrutor'] : '';
        $this->audit->log($request, 'Alteração de Curso', 'Curso "'.$atual->titulo.'" ('.$cursoId.') passou para '.$nome.'.');

        return $this->voltar($cursoId)->with('avisoDosCursos', 'Professor de "'.$atual->titulo.'" alterado para '.$nome.'.');
    }

    public function salvarPropriedades(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $dados = $request->validate(['tipo_curso' => ['required', 'in:fixo,ao_vivo']]);
        $atual = $this->curso($cursoId);
        $chat = $request->boolean('tem_chat');

        try {
            $this->cursos->updateCourse($cursoId, ['tipo_curso' => $dados['tipo_curso'], 'tem_chat' => $chat], $this->admin($request));
        } catch (ApiException $e) {
            return $this->voltar($cursoId)->withErrors(['tipo_curso' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Alteração de Curso',
            'Curso "'.$atual->titulo.'" ('.$cursoId.'): tipo '.$dados['tipo_curso'].', chat '.($chat ? 'ativado' : 'desativado').'.',
        );

        return $this->voltar($cursoId)->with('avisoDosCursos', 'Tipo e chat de "'.$atual->titulo.'" salvos.');
    }

    /**
     * Vigência de exibição.
     *
     * O administrador pode escolher qualquer data, inclusive uma já passada: é
     * assim que ele encerra um curso de propósito. Por isso não há a regra "hoje
     * ou depois" que a tela do gestor tem. A conta do "mais um ano" é feita
     * aqui, no relógio do servidor, e não no navegador.
     */
    public function salvarVigencia(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $dados = $request->validate([
            'prazo' => ['required', 'in:1-ano,permanente,data'],
            'ate' => ['required_if:prazo,data', 'nullable', 'date_format:Y-m-d'],
        ], [
            'ate.required_if' => 'Escolha a data de vigência.',
            'ate.date_format' => 'Data inválida.',
        ]);

        $atual = $this->curso($cursoId);

        $nova = match ($dados['prazo']) {
            '1-ano' => Fuso::agora()->addYear()->format('Y-m-d'),
            'data' => (string) $dados['ate'],
            default => null,
        };

        try {
            $this->cursos->updateCourse($cursoId, ['vencimento_contrato_texto' => $nova], $this->admin($request));
        } catch (ApiException $e) {
            return $this->voltar($cursoId)->withErrors(['prazo' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Alteração de Curso',
            'Vigência do curso '.$cursoId.' definida como '.($nova ?? 'permanente').'.',
        );

        return $this->voltar($cursoId)->with(
            'avisoDosCursos',
            $nova === null
                ? 'Vigência de "'.$atual->titulo.'": sem limite (permanente).'
                : 'Vigência de "'.$atual->titulo.'" até '.VigenciaDoCurso::diaParaPessoa(['vigencia_ate' => $nova]).'.',
        );
    }

    /** Trocar ou tirar a capa. O envio só vira capa depois de gravado no servidor. */
    public function salvarCapa(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $atual = $this->curso($cursoId);
        $admin = $this->admin($request);

        if ($request->boolean('remover')) {
            $this->cursos->updateCourse($cursoId, ['imagem_capa' => null], $admin);
            $this->audit->log($request, 'Alteração de Curso', 'Capa do curso '.$cursoId.' removida.');

            return $this->voltar($cursoId)->with('avisoDosCursos', 'Capa de "'.$atual->titulo.'" removida.');
        }

        $request->validate([
            'capa' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [
            'capa.required' => 'Escolha uma imagem para a capa.',
            'capa.mimes' => 'A capa precisa ser PNG, JPEG ou WebP.',
            'capa.max' => 'A imagem passa de 4 MB, o limite da capa.',
            'capa.uploaded' => 'A imagem não pôde ser recebida: ela é maior do que o servidor aceita.',
        ]);

        $arquivo = $request->file('capa');
        if ($arquivo === null || ! $arquivo->isValid()) {
            return $this->voltar($cursoId)->withErrors(['capa' => 'A imagem não pôde ser recebida.']);
        }
        if (config('features.uploadArquivos') !== true) {
            return $this->voltar($cursoId)->withErrors(['capa' => 'O envio de arquivos está desligado nesta instalação.']);
        }

        try {
            $url = $this->uploads->store($arquivo, 'public', $admin['sub'])['url'];
            $this->cursos->updateCourse($cursoId, ['imagem_capa' => $url], $admin);
        } catch (ApiException $e) {
            return $this->voltar($cursoId)->withErrors(['capa' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Alteração de Curso', 'Capa do curso '.$cursoId.' atualizada.');

        return $this->voltar($cursoId)->with('avisoDosCursos', 'Capa de "'.$atual->titulo.'" atualizada.');
    }

    /**
     * Tirar a disciplina do ar. Inativa — não apaga (ADR 12): aulas, avaliações,
     * matrículas e o histórico de quem a cursou ficam preservados, com quem
     * retirou, quando e por quê.
     */
    public function retirar(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $dados = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);
        $atual = $this->curso($cursoId);
        $motivo = trim((string) ($dados['motivo'] ?? ''));

        $this->cursos->deleteCourse($cursoId, $this->admin($request), $motivo !== '' ? $motivo : null);
        $this->audit->log($request, 'Inativação de Curso', 'Curso '.$cursoId.' inativado (registro preservado).', 'WARNING');

        return redirect('/admin/cursos')->with(
            'avisoDosCursos',
            'Curso "'.$atual->titulo.'" retirado do ar. O registro e o histórico ficam preservados.',
        );
    }

    /**
     * Devolver ao ar um curso retirado. Volta tudo de uma vez — aulas, avaliações,
     * matrículas, histórico — e para todos os gestores do curso e para os alunos,
     * porque a retirada só inativou a linha do curso (ver `CourseService::reactivateCourse`).
     */
    public function reativar(Request $request, string $cursoId): RedirectResponse
    {
        abort_unless(config('features.catalogoCursos') === true, 404);

        $curso = $this->cursos->reactivateCourse($cursoId, $this->admin($request));
        $this->audit->log($request, 'Reativação de Curso', 'Curso '.$cursoId.' reativado (aulas, matrículas e histórico preservados).', 'WARNING');

        return redirect('/admin/cursos')->with('avisoDosCursos', $this->avisoDaReativacao($curso));
    }

    /**
     * O que a pessoa precisa saber depois de reativar. O curso volta COMO ESTAVA, então
     * o que o deixaria fechado aos alunos é dito, em vez de a tela afirmar um "no ar" que
     * o aluno não veria: não se publica nem se estende vigência por baixo dos panos.
     */
    private function avisoDaReativacao(Curso $curso): string
    {
        $ressalvas = [];

        // Sem valor gravado, o curso sempre foi tratado como publicado (mesma leitura de `CursosDoAdmin::listar`).
        $status = $curso->getAttribute('status');
        $visibilidade = is_numeric($status) ? (int) $status : Visibilidade::PUBLICADO;
        if ($visibilidade === Visibilidade::RESTRITO || $visibilidade === Visibilidade::RASCUNHO) {
            $ressalvas[] = 'Ele voltou como '.($visibilidade === Visibilidade::RESTRITO ? 'Restrito' : 'Rascunho')
                .', então os alunos só o veem depois de publicado.';
        }

        $vigencia = [
            'vigencia_ate' => $curso->getAttribute('vigencia_ate'),
            'vencimento_contrato_texto' => $curso->vencimento_contrato_texto,
        ];
        if (VigenciaDoCurso::vencida($vigencia)) {
            $ressalvas[] = 'A vigência venceu em '.(VigenciaDoCurso::diaParaPessoa($vigencia) ?? 'data anterior')
                .'; enquanto não for ampliada, não entram matrículas novas.';
        }

        $gestorId = is_string($curso->instrutor_id) ? $curso->instrutor_id : '';
        $gestor = $gestorId !== '' ? Usuario::withTrashed()->find($gestorId, ['id', 'nome', 'inativado_em']) : null;
        if ($gestor !== null && $gestor->trashed()) {
            $ressalvas[] = 'O gestor do curso ('.$gestor->nome.') está retirado; atribua outro em "Gerenciar este curso".';
        }

        $base = 'Curso "'.$curso->titulo.'" voltou ao ar, com as aulas, as matrículas e o histórico de antes.';

        return $ressalvas === []
            ? $base.' Já está aberto a matrículas.'
            : $base.' Atenção: '.implode(' ', $ressalvas);
    }

    /** @return array{sub:string,nome:string,papel:string} */
    private function admin(Request $request): array
    {
        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');

        return $admin;
    }

    private function curso(string $cursoId): Curso
    {
        $curso = Curso::query()->find($cursoId);
        abort_if($curso === null, 404);

        return $curso;
    }

    private function voltar(string $cursoId): RedirectResponse
    {
        return redirect('/admin/cursos?curso='.rawurlencode($cursoId).'#curso-'.$cursoId);
    }
}
