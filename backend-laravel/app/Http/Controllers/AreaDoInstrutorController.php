<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuditLogger;
use App\Services\CaixaDeMensagens;
use App\Services\CourseService;
use App\Services\EnrollmentService;
use App\Services\LearningService;
use App\Services\MessagingService;
use App\Services\PainelDoInstrutor;
use App\Services\UploadService;
use App\Services\VersoesDeConteudo;
use App\Support\BusinessRules;
use App\Support\CanalDeMensagens;
use App\Support\ConteudoDaAula;
use App\Support\DuracaoDaGrade;
use App\Support\EdicaoDaAula;
use App\Support\Fuso;
use App\Support\MaterialDaAula;
use App\Support\QuestaoDeProva;
use App\Support\VideoProvider;
use App\Support\VideoSource;
use App\Support\VigenciaDoCurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Área do instrutor em Blade — fase M4 do plano 16.
 *
 * Como na área do aluno, nenhuma regra de negócio mora aqui: posse de curso,
 * escopo de alunos e o que cada papel alcança continuam nos services que a API
 * usa. Migrar a tela não pode mudar quem alcança o quê.
 */
final class AreaDoInstrutorController extends Controller
{
    public function __construct(
        private readonly PainelDoInstrutor $painel,
        private readonly CourseService $cursos,
        private readonly AuditLogger $audit,
        private readonly LearningService $aprendizagem,
        private readonly EnrollmentService $matriculas,
        private readonly CaixaDeMensagens $caixa,
        private readonly MessagingService $mensagens,
        private readonly UploadService $uploads,
        private readonly VersoesDeConteudo $versoes,
    ) {}

    /**
     * `/inst` — a escolha de curso, e a fila do que há para fazer.
     *
     * Esta tela existe porque grade, avaliações e alunos são sempre de UM
     * curso. O painel React nasceu sem ela: `selectedCourseId` começava no
     * primeiro curso da lista, então quem entrava já estava dentro de um curso
     * que não escolheu, e as abas operavam sobre ele.
     */
    public function escolha(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        return view('instrutor.escolha', [
            'cursos' => $this->painel->cursos($instrutor),
            'aFazer' => $this->painel->aFazer($instrutor),
        ]);
    }

    /**
     * `/inst/mensagens` — as dúvidas dos alunos e a resposta do gestor.
     *
     * Uma página para a caixa inteira, por decisão do responsável em 21/10. A
     * conversa aberta vem de `?aluno=`; sem ele, a tela pede a escolha.
     *
     * Quem decide o que este gestor alcança é o `MessagingService`, o mesmo da
     * API: pedir o aluno de outro instrutor devolve conversa vazia.
     */
    public function mensagens(Request $request): View
    {
        abort_unless(CanalDeMensagens::abertoParaInstrutor(), 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $conversas = $this->caixa->conversas($instrutor);

        $pedido = $request->query('aluno');
        $pedido = is_string($pedido) && $pedido !== '' ? $pedido : null;

        $aberta = null;
        foreach ($conversas as $conversa) {
            if ($conversa['alunoId'] === $pedido) {
                $aberta = $conversa;
                break;
            }
        }

        return view('instrutor.mensagens', [
            'conversas' => $conversas,
            'aberta' => $aberta,
            'mensagens' => $aberta === null ? [] : $this->caixa->conversa($instrutor, $aberta['alunoId']),
            'aguardando' => count(array_filter($conversas, static fn (array $c): bool => $c['aguardando'])),
        ]);
    }

    /**
     * Responder um aluno.
     *
     * O escopo é do serviço: desde 21/10 o instrutor só escreve para alunos
     * dos cursos dele — antes a leitura era escopada e a escrita não.
     */
    public function responder(Request $request): RedirectResponse
    {
        abort_unless(CanalDeMensagens::abertoParaInstrutor(), 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $dados = $request->validate([
            'aluno' => ['required', 'string', 'max:191'],
            'texto' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $destino = '/inst/mensagens?aluno='.urlencode((string) $dados['aluno']);

        try {
            $this->mensagens->createDirectMessage([
                'studentUserId' => (string) $dados['aluno'],
                'text' => trim((string) $dados['texto']),
            ], $instrutor);
        } catch (ApiException $e) {
            return redirect($destino)->withInput()->withErrors(['texto' => $e->getMessage()]);
        }

        return redirect($destino)->with('respostaEnviada', true);
    }

    /**
     * `/inst/curso/{ref}` — a Gestão do Curso.
     *
     * O que o curso é, o que ele tem dentro, e as duas alterações que cabem
     * aqui: as propriedades e a vigência. Grade, avaliações e alunos têm tela
     * própria, e esta só aponta para elas.
     *
     * O que NÃO veio do painel React: os webinars (fora do MVP, decisão de
     * 21/10) e o botão "Acessar Sala", que setava a sala ao vivo e não abria
     * nada, porque `liveClassroom` está desligada.
     */
    public function curso(Request $request, string $cursoRef): View|RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        return view('instrutor.curso', $this->painel->panoramaDoCurso($meu['curso'], $instrutor) + [
            'ref' => $meu['ref'],
        ]);
    }

    /**
     * O curso que este instrutor gerencia, resolvido pelo endereço.
     *
     * A posse vem do `CourseService` (FK `instructorId`, ADR 10) e é a mesma
     * que a API aplica — `updateCourse` a reconfere antes de gravar, então uma
     * falha aqui não vira escrita indevida; o que ela evitaria é a tela de um
     * curso alheio.
     *
     * @param  array{sub:string,name:string,role:string}  $instrutor
     * @return array{curso:array<string, mixed>,id:string,ref:string}|RedirectResponse
     */
    private function meuCurso(array $instrutor, string $cursoRef, string $resto = ''): array|RedirectResponse
    {
        $resolvido = $this->cursos->resolverCurso($cursoRef, $instrutor['role']);

        $curso = $this->cursos->getCourseById($resolvido['id']);
        $souDono = ($curso['instructorId'] ?? null) === $instrutor['sub'];
        abort_unless($instrutor['role'] === 'admin' || $souDono, 404);

        if (! $resolvido['canonico'] && $resolvido['slug'] !== '') {
            return redirect('/inst/curso/'.$resolvido['slug'].$resto);
        }

        return [
            'curso' => $curso,
            'id' => $resolvido['id'],
            'ref' => $resolvido['slug'] !== '' ? $resolvido['slug'] : $resolvido['id'],
        ];
    }

    /**
     * Salvar as propriedades do curso.
     *
     * Quem grava é o `CourseService::updateCourse`, o mesmo da API: ele
     * reconfere a posse, deriva o slug do título e registra o histórico do slug
     * antigo. A auditoria é a mesma linha que o `CourseController` escreve —
     * sem ela, a alteração pela tela sumiria do registro que a feita pela API
     * deixa.
     */
    public function salvarPropriedades(Request $request, string $cursoRef): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        // Os mesmos limites do `CourseController::validated`, porque é o mesmo
        // serviço atrás: dois conjuntos de regras divergiriam.
        $dados = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', 'string', 'min:1', 'max:120'],
            'cargaHoraria' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'modalidade' => ['nullable', 'string', 'max:60'],
            'nivel' => ['nullable', 'string', 'max:60'],
            'areaTematica' => ['nullable', 'string', 'max:150'],
            'minAttendance' => ['nullable', 'integer', 'min:0', 'max:100'],
            'emiteCertificado' => ['nullable', 'boolean'],
        ], [], [
            'title' => 'título',
            'description' => 'descrição',
            'category' => 'categoria',
            'cargaHoraria' => 'carga horária',
            'minAttendance' => 'frequência mínima',
        ]);

        $atualizacao = [
            'title' => trim((string) $dados['title']),
            'description' => trim((string) $dados['description']),
            'category' => trim((string) $dados['category']),
            'cargaHoraria' => $dados['cargaHoraria'] ?? null,
            'modalidade' => self::vazioVirouNulo($dados['modalidade'] ?? null),
            'nivel' => self::vazioVirouNulo($dados['nivel'] ?? null),
            'areaTematica' => self::vazioVirouNulo($dados['areaTematica'] ?? null),
            'minAttendance' => $dados['minAttendance'] ?? null,
            'emiteCertificado' => $request->boolean('emiteCertificado'),
        ];

        /*
         * `cargaHoraria` nula é recusada pelo serviço (`integer|min:1`), então
         * campo em branco significa "não mexer", e não "zerar".
         */
        if ($atualizacao['cargaHoraria'] === null) {
            unset($atualizacao['cargaHoraria']);
        }
        if ($atualizacao['minAttendance'] === null) {
            unset($atualizacao['minAttendance']);
        }

        try {
            $this->cursos->updateCourse($meu['id'], $atualizacao, $instrutor);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['title' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Alteração de Curso', 'Curso "'.$atualizacao['title'].'" ('.$meu['id'].') atualizado pela tela de gestão.');

        // O título muda o slug: o endereço de hoje pode ser outro.
        $novo = $this->cursos->getCourseById($meu['id']);
        $ref = is_string($novo['slug'] ?? null) && $novo['slug'] !== '' ? $novo['slug'] : $meu['id'];

        return redirect('/inst/curso/'.$ref)->with('avisoDoCurso', 'Propriedades salvas.');
    }

    /**
     * Renovar (ou encerrar) a vigência de exibição.
     *
     * Os atalhos são os do painel React — +6 meses, +1 ano, permanente — mais
     * uma data escolhida. A conta é feita no SERVIDOR: no React ela era feita
     * com o relógio do navegador, que pode estar em qualquer fuso ou
     * simplesmente errado.
     *
     * Vigência é licença de exibição, e é ela que decide se o curso pode ser
     * oferecido (ver `App\Support\VigenciaDoCurso`) — por isso a alteração
     * é auditada como as outras.
     */
    public function salvarVigencia(Request $request, string $cursoRef): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate([
            'prazo' => ['required', 'in:6-meses,1-ano,permanente,data'],
            'ate' => ['required_if:prazo,data', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'ate.required_if' => 'Escolha a data até quando o curso fica no ar.',
            'ate.after_or_equal' => 'A data precisa ser hoje ou depois.',
            'ate.date_format' => 'Data inválida.',
        ]);

        $hoje = Fuso::agora();
        $nova = match ($dados['prazo']) {
            '6-meses' => $hoje->addMonths(6)->format('Y-m-d'),
            '1-ano' => $hoje->addYear()->format('Y-m-d'),
            'data' => (string) $dados['ate'],
            // Permanente é a AUSÊNCIA de prazo, e `null` é o que o serviço
            // entende: ele deriva `vigenciaAte` do texto, e texto vazio vira
            // uma data nula.
            default => null,
        };

        try {
            $this->cursos->updateCourse($meu['id'], ['contractExpirationDate' => $nova], $instrutor);
        } catch (ApiException $e) {
            return back()->withErrors(['prazo' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Alteração de Curso',
            'Vigência do curso '.$meu['id'].' definida como '.($nova ?? 'permanente').'.',
        );

        return redirect('/inst/curso/'.$meu['ref'])->with(
            'avisoDoCurso',
            $nova === null ? 'Vigência agora é permanente.' : 'Vigência renovada até '.VigenciaDoCurso::diaParaPessoa(['vigenciaAte' => $nova]).'.',
        );
    }

    /**
     * `/inst/curso/{ref}/grade-curricular` — as aulas do curso, em ordem.
     *
     * Só a LISTA é do servidor: mudar a ordem e tirar uma aula do ar. O que
     * mexe no CONTEÚDO da aula (texto, vídeo, anexos) é o editor, que continua
     * em React até a fase M6 do plano 16 e tem endereço próprio —
     * `/inst/curso/{ref}/aula/{id}` e `/aula/nova`.
     */
    public function grade(Request $request, string $cursoRef): View|RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, '/grade-curricular');
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $aulas = is_array($meu['curso']['lessons'] ?? null) ? array_values($meu['curso']['lessons']) : [];

        return view('instrutor.grade', [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'aulas' => array_map(static function (mixed $aula): array {
                $a = is_array($aula) ? $aula : [];
                $documentos = is_array($a['documents'] ?? null) ? $a['documents'] : [];

                return [
                    'id' => is_string($a['id'] ?? null) ? $a['id'] : '',
                    'title' => is_string($a['title'] ?? null) ? $a['title'] : '',
                    'duration' => is_string($a['duration'] ?? null) && $a['duration'] !== '' ? $a['duration'] : null,
                    'temVideo' => is_string($a['videoUrl'] ?? null) && $a['videoUrl'] !== '',
                    'documentos' => count($documentos),
                ];
            }, $aulas),
            'tempoTotal' => DuracaoDaGrade::texto(array_map(
                static fn (mixed $a): array => is_array($a) ? $a : [],
                $aulas,
            )),
            'vigenciaVencida' => VigenciaDoCurso::vencida($meu['curso']),
            'vigenciaAte' => VigenciaDoCurso::diaParaPessoa($meu['curso']),
        ]);
    }

    /**
     * Sobe ou desce uma aula na grade.
     *
     * Quem grava é o `CourseService::moverAula`, que troca a aula com a vizinha
     * e renumera — e que só mexe na ORDEM. O React reenviava o curso inteiro
     * (`PUT /api/courses/{id}` com todas as aulas), e dois instrutores
     * editando o mesmo curso se sobrescreviam sem aviso.
     */
    public function moverAula(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate(['direcao' => ['required', 'in:up,down']]);

        try {
            $this->cursos->moverAula($meu['id'], $aulaId, $dados['direcao'] === 'up' ? 'up' : 'down', $instrutor);
        } catch (ApiException $e) {
            return redirect('/inst/curso/'.$meu['ref'].'/grade-curricular')->withErrors(['grade' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Alteração de Curso', 'Ordem das aulas do curso '.$meu['id'].' alterada.');

        // O foco volta para a aula que se moveu: sem a âncora, a página recarrega
        // no topo e quem usa teclado perde o lugar a cada clique.
        return redirect('/inst/curso/'.$meu['ref'].'/grade-curricular#aula-'.rawurlencode($aulaId));
    }

    /**
     * Tira uma aula do ar. Não apaga (ADR 12): a aula fica no banco com quem
     * tirou e quando, e o progresso dos alunos que apontava para ela é limpo.
     */
    public function removerAula(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $titulo = '';
        foreach (is_array($meu['curso']['lessons'] ?? null) ? $meu['curso']['lessons'] : [] as $aula) {
            if (is_array($aula) && ($aula['id'] ?? null) === $aulaId && is_string($aula['title'] ?? null)) {
                $titulo = $aula['title'];
            }
        }

        try {
            $this->cursos->inativarAula($meu['id'], $aulaId, $instrutor, 'Removida da grade pelo instrutor.');
        } catch (ApiException $e) {
            return redirect('/inst/curso/'.$meu['ref'].'/grade-curricular')->withErrors(['grade' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Alteração de Curso',
            'Aula "'.$titulo.'" ('.$aulaId.') do curso '.$meu['id'].' retirada da grade (registro preservado).',
            'WARNING',
        );

        return redirect('/inst/curso/'.$meu['ref'].'/grade-curricular')
            ->with('avisoDoCurso', 'Aula retirada da grade.'.($titulo === '' ? '' : ' “'.$titulo.'” continua guardada no sistema.'));
    }

    /**
     * `/inst/curso/{ref}/aulas/nova` — o formulário da aula nova.
     *
     * A aula nasce com título, carga e texto de estudo (o painel React exigia os
     * três), e o vídeo é opcional. Depois de criada, quem abre é a página da aula,
     * onde o texto se edita bloco a bloco.
     */
    public function formularioDeAula(Request $request, string $cursoRef): View|RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, '/aulas/nova');
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        return view('instrutor.aula-nova', ['curso' => $meu['curso'], 'ref' => $meu['ref']]);
    }

    /** Cria a aula no fim da grade e abre a página dela. */
    public function criarAula(Request $request, string $cursoRef): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate([
            'titulo' => ['nullable', 'string'],
            'carga' => ['nullable', 'string'],
            'video' => ['nullable', 'string'],
            'texto' => ['nullable', 'string'],
        ]);

        try {
            $id = $this->cursos->criarAula($meu['id'], [
                'title' => $dados['titulo'] ?? '',
                'duration' => $dados['carga'] ?? '',
                'videoUrl' => $dados['video'] ?? null,
                'content' => $dados['texto'] ?? '',
            ], $instrutor);
        } catch (ApiException $e) {
            return redirect('/inst/curso/'.$meu['ref'].'/aulas/nova')->withErrors(['aula' => $e->getMessage()])->withInput();
        }

        $this->audit->log($request, 'Alteração de Curso', 'Aula "'.trim($dados['titulo'] ?? '').'" ('.$id.') criada no curso '.$meu['id'].'.');

        return redirect('/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($id))->with('avisoDoCurso', 'Aula criada no fim da grade.');
    }

    /**
     * `/inst/curso/{ref}/aulas/{id}` — a página de UMA aula, com as quatro zonas
     * na ordem em que o aluno as recebe: identificação, vídeo, material didático
     * e anexos.
     *
     * A zona aberta para edição vem do endereço (`?editar=video`), e não de
     * JavaScript: o F5 no meio da edição volta para o mesmo formulário, e o erro
     * de validação reabre a zona certa.
     */
    public function aula(Request $request, string $cursoRef, string $aulaId): View|RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, '/aulas/'.rawurlencode($aulaId));
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $achada = $this->aulaDoCurso($meu['curso'], $aulaId);
        if ($achada === null) {
            // Link velho, id digitado, aula já retirada: a grade é o lugar certo.
            return redirect('/inst/curso/'.$meu['ref'].'/grade-curricular')
                ->withErrors(['grade' => 'Essa aula não existe mais neste curso.']);
        }

        $editar = $request->query('editar');
        $conteudo = is_string($achada['aula']['content'] ?? null) ? $achada['aula']['content'] : '';

        return view('instrutor.aula', [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'aula' => $achada['aula'],
            'posicao' => $achada['posicao'],
            'total' => $achada['total'],
            'documentos' => is_array($achada['aula']['documents'] ?? null) ? array_values($achada['aula']['documents']) : [],
            'blocos' => ConteudoDaAula::analisar($conteudo),
            'conteudo' => $conteudo,
            'versao' => CourseService::versaoDoConteudo($conteudo),
            'editar' => is_string($editar) && in_array($editar, ['identificacao', 'video', 'material', 'anexos'], true) ? $editar : null,
            // Dentro do material: qual bloco está com o formulário aberto, onde o
            // menu "Adicionar" está aberto, e a mídia nova em criação. Tudo vem do
            // endereço, como a zona.
            'editarBloco' => self::inteiroDaQuery($request->query('bloco'), 0),
            'adicionarEm' => self::inteiroDaQuery($request->query('adicionar'), -1),
            'novo' => in_array($request->query('novo'), ['imagem', 'video'], true) ? $request->query('novo') : null,
            'apos' => self::inteiroDaQuery($request->query('apos'), -1) ?? -1,
            'uploadAtivo' => config('features.uploadArquivos') === true,
            'versoes' => $this->versoesDaAula($aulaId),
        ]);
    }

    /**
     * As versões anteriores da aula, prontas para a tela: quando, quem substituiu,
     * um resumo e o texto como o aluno o veria (ADR 021).
     *
     * @return list<array{id:string,quando:string,porNome:?string,resumo:string,blocos:list<array<string, mixed>>}>
     */
    private function versoesDaAula(string $aulaId): array
    {
        $saida = [];
        foreach ($this->versoes->listar(VersoesDeConteudo::AULA, $aulaId) as $v) {
            $titulo = is_string($v['conteudo']['title'] ?? null) ? $v['conteudo']['title'] : '';
            $texto = is_string($v['conteudo']['content'] ?? null) ? $v['conteudo']['content'] : '';

            $saida[] = [
                'id' => $v['id'],
                'quando' => Fuso::de($v['criadoEm'])->format('d/m/Y H:i'),
                'porNome' => $v['porNome'],
                'resumo' => ($titulo !== '' ? mb_strimwidth($titulo, 0, 90, '…') : 'Sem título').' · '.mb_strlen($texto).' caracteres de texto',
                'blocos' => ConteudoDaAula::analisar($texto)['blocos'],
            ];
        }

        return $saida;
    }

    /**
     * Volta a aula a uma versão anterior (título, carga, vídeo e texto). O que
     * estava no ar vira uma nova versão, então dá para desfazer a restauração.
     * Os anexos não entram: são registros próprios, com a retirada deles já
     * preservada pela regra de nada ser apagado.
     */
    public function restaurarVersao(Request $request, string $cursoRef, string $aulaId, string $versao): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }
        abort_if($this->aulaDoCurso($meu['curso'], $aulaId) === null, 404);

        $anterior = $this->versoes->obter(VersoesDeConteudo::AULA, $aulaId, $versao);
        abort_if($anterior === null, 404);

        $pagina = '/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($aulaId);

        try {
            $this->cursos->atualizarAula($meu['id'], $aulaId, [
                'title' => is_string($anterior['title'] ?? null) ? $anterior['title'] : '',
                'duration' => is_string($anterior['duration'] ?? null) ? $anterior['duration'] : '',
                'videoUrl' => is_string($anterior['videoUrl'] ?? null) ? $anterior['videoUrl'] : null,
                'content' => is_string($anterior['content'] ?? null) ? $anterior['content'] : null,
            ], $instrutor);
        } catch (ApiException $e) {
            return redirect($pagina.'#historico')->withErrors(['aula' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Alteração de Curso', 'Aula '.$aulaId.' do curso '.$meu['id'].' voltou a uma versão anterior ('.$versao.').');

        return redirect($pagina)->with('avisoDoCurso', 'A versão anterior da aula voltou ao ar. O que estava publicado ficou guardado no histórico.');
    }

    /**
     * A prévia ao vivo de UM trecho do material: o mesmo formulário de edição, o
     * mesmo texto gravado e o mesmo componente que o aluno usa — só que sem
     * gravar. Por isso a prévia não tem regra de formatação própria: se o aluno
     * vai ver diferente, é o componente que mudou, e a prévia muda junto.
     *
     * Devolve um trecho de HTML (já escapado pelo Blade), ou 422 com a mensagem
     * da regra que o trecho quebraria — a mesma que o "Aplicar" mostraria.
     */
    public function previa(Request $request, string $cursoRef, string $aulaId): Response
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        abort_if($meu instanceof RedirectResponse, 404);
        /** @var array{curso:array<string, mixed>,id:string,ref:string} $meu */
        $achada = $this->aulaDoCurso($meu['curso'], $aulaId);
        abort_if($achada === null, 404);

        $indice = max(0, $request->integer('indice'));
        $atual = is_string($achada['aula']['content'] ?? null) ? $achada['aula']['content'] : '';

        $campos = [];
        foreach (['tipo', 'texto', 'tamanho', 'alinhamento', 'linguagem', 'legenda', 'codigo', 'url', 'descricao'] as $chave) {
            $valor = $request->input($chave);
            if (is_string($valor)) {
                $campos[$chave] = $valor;
            }
        }

        try {
            // A imagem fica com o endereço que o bloco já tem: ele vem do TEXTO gravado, nunca do formulário.
            $bloco = MaterialDaAula::bloco($atual, $indice);
            if (($bloco['tipo'] ?? null) === 'imagem') {
                $campos['url'] = is_string($bloco['url'] ?? null) ? $bloco['url'] : '';
            }

            $novo = MaterialDaAula::atualizar($atual, $indice, $campos);
            $blocos = ConteudoDaAula::analisarComTrechos($novo)['blocos'];
        } catch (InvalidArgumentException $e) {
            return response(view('instrutor.previa-do-trecho', ['erro' => $e->getMessage(), 'blocos' => []])->render(), 422);
        }

        return response(view('instrutor.previa-do-trecho', ['erro' => null, 'blocos' => isset($blocos[$indice]) ? [$blocos[$indice]] : []])->render());
    }

    /** Um número inteiro do endereço, a partir de `$minimo`; qualquer outra coisa é "não pedido". */
    private static function inteiroDaQuery(mixed $valor, int $minimo): ?int
    {
        if (! is_string($valor) || preg_match('/^-?\d{1,6}$/', $valor) !== 1) {
            return null;
        }

        $n = (int) $valor;

        return $n >= $minimo ? $n : null;
    }

    /** Salva título e carga da aula — e só eles. */
    public function salvarIdentificacao(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        $dados = $request->validate(['titulo' => ['nullable', 'string'], 'carga' => ['nullable', 'string']]);

        return $this->gravarAula($request, $cursoRef, $aulaId, 'identificacao', [
            'title' => $dados['titulo'] ?? '',
            'duration' => $dados['carga'] ?? '',
        ], 'Identificação da aula atualizada.');
    }

    /** Salva o vídeo da aula; vazio tira o vídeo. */
    public function salvarVideo(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        $dados = $request->validate(['video' => ['nullable', 'string']]);
        $video = trim($dados['video'] ?? '');

        return $this->gravarAula($request, $cursoRef, $aulaId, 'video', ['videoUrl' => $video], $video === '' ? 'Vídeo removido da aula.' : 'Vídeo da aula atualizado.');
    }

    /**
     * A escrita comum das zonas: resolve o curso, grava via `CourseService` (que
     * reconfere a posse) e volta para a página da aula. Erro do serviço reabre a
     * zona com o que a pessoa digitou.
     *
     * @param  array<string, mixed>  $campos
     */
    private function gravarAula(Request $request, string $cursoRef, string $aulaId, string $zona, array $campos, string $aviso): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $pagina = '/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($aulaId);

        try {
            /** @var array{title?:string,duration?:string,videoUrl?:?string,content?:?string} $campos */
            $this->cursos->atualizarAula($meu['id'], $aulaId, $campos, $instrutor);
        } catch (ApiException $e) {
            return redirect($pagina.'?editar='.$zona.'#'.$zona)->withErrors([$zona => $e->getMessage()])->withInput();
        }

        $this->audit->log($request, 'Alteração de Curso', 'Aula '.$aulaId.' do curso '.$meu['id'].' atualizada ('.$zona.').');

        return redirect($pagina.'#'.$zona)->with('avisoDoCurso', $aviso);
    }

    /**
     * Todas as escritas do material didático, num só endereço: o campo `acao`
     * diz qual — mover, remover, atualizar ou inserir um bloco.
     *
     * Cada ação grava NA HORA e só o texto da aula, com a trava de versão do
     * `CourseService::editarConteudoDaAula`: se o texto mudou desde que a pessoa
     * abriu a página, nada é aplicado e ela recebe o aviso para recarregar. O
     * painel React guardava um rascunho no navegador até o "Salvar material" e
     * então regravava o curso inteiro; aqui não há rascunho para perder.
     *
     * A imagem enviada passa pelo mesmo `UploadService` da API. O índice do bloco
     * vem da leitura que a pessoa viu — por isso a versão: um índice velho
     * trocaria o trecho errado.
     */
    public function material(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate([
            'acao' => ['required', 'in:mover,remover,atualizar,inserir'],
            'versao' => ['required', 'string'],
            'indice' => ['nullable', 'integer', 'min:0'],
            'apos' => ['nullable', 'integer', 'min:-1'],
            'direcao' => ['nullable', 'in:up,down'],
        ]);

        $acao = $dados['acao'];
        $indice = (int) ($dados['indice'] ?? 0);
        $apos = (int) ($dados['apos'] ?? -1);
        $pagina = '/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($aulaId);
        $campos = [];
        foreach (['tipo', 'texto', 'tamanho', 'alinhamento', 'linguagem', 'legenda', 'codigo', 'url', 'descricao'] as $chave) {
            $valor = $request->input($chave);
            if (is_string($valor)) {
                $campos[$chave] = $valor;
            }
        }

        // O tipo pedido (imagem, vídeo, ou o tipo de texto de destino), lido uma vez.
        $tipoPedido = $campos['tipo'] ?? '';

        $aula = $this->aulaDoCurso($meu['curso'], $aulaId);
        $videoPrincipal = is_string($aula['aula']['videoUrl'] ?? null) ? $aula['aula']['videoUrl'] : null;
        $removido = null;
        $avisos = [];

        try {
            // A imagem sobe ANTES da transação: o arquivo é gravado em disco e não
            // se desfaz com ela. Se a versão estiver velha, sobra um arquivo sem
            // uso, que não aparece em aula nenhuma.
            $arquivo = $request->file('arquivo');
            if ($arquivo instanceof UploadedFile && in_array($acao, ['atualizar', 'inserir'], true)) {
                $campos['url'] = $this->enviarImagem($arquivo, $instrutor['sub'], $avisos);
            }

            $this->cursos->editarConteudoDaAula(
                $meu['id'],
                $aulaId,
                $dados['versao'],
                function (string $atual) use ($acao, $indice, $apos, $dados, $tipoPedido, &$campos, &$removido, $arquivo): string {
                    try {
                        return match ($acao) {
                            'mover' => MaterialDaAula::mover($atual, $indice, $dados['direcao'] ?? 'up'),
                            'remover' => (static function () use ($atual, $indice, &$removido): string {
                                $removido = EdicaoDaAula::serializar(MaterialDaAula::bloco($atual, $indice));

                                return MaterialDaAula::remover($atual, $indice);
                            })(),
                            'atualizar' => (function () use ($atual, $indice, &$campos, $arquivo): string {
                                // Sem arquivo novo, a imagem fica com o endereço que o bloco já
                                // tem — vindo do TEXTO gravado, e nunca do formulário.
                                $bloco = MaterialDaAula::bloco($atual, $indice);
                                if (($bloco['tipo'] ?? null) === 'imagem' && ! $arquivo instanceof UploadedFile) {
                                    $campos['url'] = is_string($bloco['url'] ?? null) ? $bloco['url'] : '';
                                }

                                return MaterialDaAula::atualizar($atual, $indice, $campos);
                            })(),
                            default => in_array($tipoPedido, ['imagem', 'video'], true)
                                ? MaterialDaAula::inserir($atual, $apos, MaterialDaAula::midiaNova($tipoPedido, $campos))
                                : MaterialDaAula::inserirModelo($atual, $apos, $tipoPedido),
                        };
                    } catch (InvalidArgumentException $e) {
                        throw ApiException::validation($e->getMessage());
                    }
                },
                $instrutor,
            );
        } catch (ApiException $e) {
            $volta = match ($acao) {
                'atualizar' => '?editar=material&bloco='.$indice.'#bloco-'.$indice,
                'inserir' => in_array($tipoPedido, ['imagem', 'video'], true)
                    ? '?editar=material&novo='.$tipoPedido.'&apos='.$apos.'#novo'
                    : '?editar=material#material',
                default => '?editar=material#material',
            };

            return redirect($pagina.$volta)->withErrors(['material' => $e->getMessage()])->withInput($request->except(['arquivo', 'versao']));
        }

        if ($removido !== null) {
            $this->audit->log(
                $request,
                'Alteração de Curso',
                'Trecho removido do material da aula '.$aulaId.' do curso '.$meu['id'].': "'.mb_substr($removido, 0, 300).'".',
                'WARNING',
            );
        } else {
            $this->audit->log($request, 'Alteração de Curso', 'Material da aula '.$aulaId.' do curso '.$meu['id'].' alterado ('.$acao.').');
        }

        if ($tipoPedido === 'video' && self::repeteOVideoPrincipal($campos['url'] ?? '', $videoPrincipal)) {
            $avisos[] = 'Este já é o vídeo principal da aula, no topo. Repetir aqui faz o aluno ver o mesmo vídeo duas vezes.';
        }

        [$destino, $aviso] = match ($acao) {
            'mover' => ['?editar=material#bloco-'.max(0, $indice + (($dados['direcao'] ?? 'up') === 'up' ? -1 : 1)), 'Trecho movido.'],
            'remover' => ['?editar=material#material', 'Trecho removido do texto da aula.'],
            'atualizar' => ['?editar=material#bloco-'.$indice, 'Trecho atualizado.'],
            default => in_array($tipoPedido, ['imagem', 'video'], true)
                ? ['?editar=material#bloco-'.($apos + 1), 'Trecho adicionado.']
                : ['?editar=material&bloco='.($apos + 1).'#bloco-'.($apos + 1), 'Trecho adicionado. Edite o texto dele abaixo.'],
        };

        $resposta = redirect($pagina.$destino)->with('avisoDoCurso', $aviso);

        return $avisos === [] ? $resposta : $resposta->with('avisosDoMaterial', $avisos);
    }

    /**
     * Envia a imagem pelo `UploadService` e devolve o endereço dela.
     *
     * Só imagem: o serviço aceita também PDF e Word, e um PDF num bloco de imagem
     * apareceria quebrado para o aluno.
     *
     * @param  list<string>  $avisos  recebe o aviso de imagem pesada
     */
    private function enviarImagem(UploadedFile $arquivo, string $donoId, array &$avisos): string
    {
        if (! $arquivo->isValid()) {
            throw ApiException::validation('O arquivo não pôde ser recebido: ele é maior do que o servidor aceita. Tente um arquivo menor.');
        }
        if (config('features.uploadArquivos') !== true) {
            throw ApiException::validation('O envio de arquivos está desligado nesta instalação.');
        }
        if (! in_array(strtolower($arquivo->getClientOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            throw ApiException::validation('Escolha um arquivo de imagem: PNG, JPG, WEBP ou GIF.');
        }

        // Acima de 2 MB o aluno sente no celular: o servidor não redimensiona nada.
        // É aviso, não bloqueio.
        if ((int) $arquivo->getSize() > 2 * 1024 * 1024) {
            $avisos[] = 'Esta imagem é pesada ('.number_format((int) $arquivo->getSize() / (1024 * 1024), 1, ',', '.').' MB) e pode demorar para abrir no celular do aluno.';
        }

        return $this->uploads->store($arquivo, 'public', $donoId)['url'];
    }

    /** Mesmo vídeo? YouTube compara pelo id; arquivo, pelo endereço. */
    private static function repeteOVideoPrincipal(string $url, ?string $principal): bool
    {
        $a = VideoSource::tryParse($url);
        $b = $principal === null ? null : VideoSource::tryParse($principal);
        if ($a === null || $b === null || $a->provider !== $b->provider) {
            return false;
        }

        return $a->provider === VideoProvider::Youtube ? $a->videoId === $b->videoId : $a->canonicalUrl === $b->canonicalUrl;
    }

    /**
     * Anexa um arquivo ou um link à aula.
     *
     * O arquivo, quando vem, é enviado pelo mesmo `UploadService` da API
     * (extensão permitida, tamanho máximo, tipo real do conteúdo, nome gerado no
     * servidor) e vira o endereço do anexo — como o React fazia pela rota
     * `/api/upload`. Arquivo e link ao mesmo tempo: o arquivo vale, como no painel
     * antigo, que desabilitava o campo de endereço depois de escolher um arquivo.
     *
     * Título, tipo e tamanho que a pessoa deixar em branco são deduzidos do que
     * ela enviou — o painel antigo os preenchia sozinho na hora, por JavaScript.
     */
    public function anexarDocumento(Request $request, string $cursoRef, string $aulaId): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate([
            'titulo' => ['nullable', 'string'],
            'tipo' => ['nullable', 'string'],
            'tamanho' => ['nullable', 'string'],
            'url' => ['nullable', 'string'],
        ]);

        $pagina = '/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($aulaId);
        $titulo = trim($dados['titulo'] ?? '');
        $tipo = trim($dados['tipo'] ?? '') === '' ? 'auto' : trim($dados['tipo'] ?? '');
        $tamanho = trim($dados['tamanho'] ?? '');
        $url = trim($dados['url'] ?? '');

        try {
            $arquivo = $request->file('arquivo');
            if ($arquivo instanceof UploadedFile) {
                if (! $arquivo->isValid()) {
                    throw ApiException::validation('O arquivo não pôde ser recebido: ele é maior do que o servidor aceita. Tente um arquivo menor.');
                }
                if (config('features.uploadArquivos') !== true) {
                    throw ApiException::validation('O envio de arquivos está desligado nesta instalação. Informe o endereço de um link.');
                }

                $enviado = $this->uploads->store($arquivo, 'public', $instrutor['sub']);
                $url = $enviado['url'];
                $tamanho = $tamanho !== '' ? $tamanho : $enviado['size'];
                $titulo = $titulo !== '' ? $titulo : self::tituloDoArquivo($enviado['fileName']);
                $tipo = $tipo === 'auto' ? self::tipoDoArquivo($enviado['fileName']) : $tipo;
            } elseif ($tipo === 'auto') {
                $tipo = preg_match('~^https?://(?:docs|drive)\.google\.com/~i', $url) === 1 ? 'drive' : 'url';
            }

            $this->cursos->anexarDocumento($meu['id'], $aulaId, [
                'title' => $titulo,
                'type' => $tipo,
                'url' => $url,
                'size' => $tamanho,
            ], $instrutor);
        } catch (ApiException $e) {
            return redirect($pagina.'?editar=anexos#anexos')
                ->withErrors(['anexos' => $e->getMessage()])
                ->withInput($request->except('arquivo'));
        }

        $this->audit->log($request, 'Alteração de Curso', 'Documento "'.$titulo.'" anexado à aula '.$aulaId.' do curso '.$meu['id'].'.');

        return redirect($pagina.'#anexos')->with('avisoDoCurso', 'Documento anexado.');
    }

    /** Desvincula um documento da aula. Não apaga (ADR 12). */
    public function desvincularDocumento(Request $request, string $cursoRef, string $aulaId, string $documentoId): RedirectResponse
    {
        abort_unless(config('features.modulosAulas') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $pagina = '/inst/curso/'.$meu['ref'].'/aulas/'.rawurlencode($aulaId);

        try {
            $this->cursos->desvincularDocumento($meu['id'], $aulaId, $documentoId, $instrutor, 'Desvinculado da aula pelo instrutor.');
        } catch (ApiException $e) {
            return redirect($pagina.'?editar=anexos#anexos')->withErrors(['anexos' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Alteração de Curso', 'Documento '.$documentoId.' desvinculado da aula '.$aulaId.' do curso '.$meu['id'].' (registro preservado).', 'WARNING');

        return redirect($pagina.'?editar=anexos#anexos')->with('avisoDoCurso', 'Documento desvinculado da aula. O registro continua guardado no sistema.');
    }

    /** "slides_aula-1.pdf" → "Slides Aula 1": o título que o painel antigo sugeria. */
    private static function tituloDoArquivo(string $nome): string
    {
        $semExtensao = pathinfo($nome, PATHINFO_FILENAME);
        $limpo = trim((string) preg_replace('/[_-]+/u', ' ', $semExtensao));

        return (string) preg_replace_callback('/\b\p{L}/u', static fn (array $m): string => mb_strtoupper($m[0]), $limpo);
    }

    private static function tipoDoArquivo(string $nome): string
    {
        return match (strtolower(pathinfo($nome, PATHINFO_EXTENSION))) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'doc',
            default => 'outro',
        };
    }

    /**
     * A aula dentro do curso já carregado, com a posição na grade.
     *
     * @param  array<string, mixed>  $curso
     * @return array{aula:array<string, mixed>,posicao:int,total:int}|null
     */
    private function aulaDoCurso(array $curso, string $aulaId): ?array
    {
        $aulas = is_array($curso['lessons'] ?? null) ? array_values($curso['lessons']) : [];

        foreach ($aulas as $i => $aula) {
            if (is_array($aula) && ($aula['id'] ?? null) === $aulaId) {
                return ['aula' => $aula, 'posicao' => $i + 1, 'total' => count($aulas)];
            }
        }

        return null;
    }

    /**
     * `/inst/curso/{ref}/avaliacoes` — as provas do curso.
     *
     * Só provas. A sub-aba "Exercícios" do painel React existe atrás de
     * `atividadesPraticasAvancadas`, que está desligada, e tem rota própria
     * (`/avaliacoes/exercicios`) que diz isso.
     */
    public function avaliacoes(Request $request, string $cursoRef): View|RedirectResponse
    {
        abort_unless(config('features.quizSimples') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, '/avaliacoes');
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        return view('instrutor.avaliacoes', [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'avaliacoes' => $this->aprendizagem->avaliacoesDoCurso($meu['id'], $instrutor['role']),
            'minimo' => BusinessRules::quizPassThreshold(),
        ]);
    }

    /**
     * O formulário de uma avaliação — a mesma tela cria e edita.
     *
     * `$quizId` nulo é prova nova. O curso vem SEMPRE do endereço: o formulário
     * não tem campo de curso, e por isso uma prova não muda de curso por edição.
     * (O editor React tinha o seletor, e a API aceita `courseId` no corpo.)
     */
    public function formularioDeAvaliacao(Request $request, string $cursoRef, ?string $quizId = null): View|RedirectResponse
    {
        abort_unless(config('features.quizSimples') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, $quizId === null ? '/avaliacoes/nova' : '/avaliacoes/'.rawurlencode($quizId).'/editar');
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $prova = null;
        if ($quizId !== null) {
            $prova = $this->aprendizagem->avaliacaoParaEditar($meu['id'], $quizId);
            abort_if($prova === null, 404);
        }

        return view('instrutor.avaliacao-form', [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'prova' => $prova,
            'inicial' => $this->questoesIniciais($prova),
            'jaResponderam' => $prova === null ? 0 : $this->aprendizagem->quantosJaResponderam($prova['id']),
            'minimo' => BusinessRules::quizPassThreshold(),
        ]);
    }

    /**
     * O que o formulário mostra ao abrir: o que a pessoa acabou de digitar (se
     * a gravação falhou), a prova que se edita, ou uma questão em branco.
     *
     * @param  array{id:string,title:string,questions:list<array{id:string,questionText:string,options:list<string>,correctOptionIndex:int,explanation:string|null,reviewMessage:string|null,recommendedModule:string|null,allowRetry:bool}>}|null  $prova
     * @return list<array{id:string,enunciado:string,alternativas:list<string>,correta:int,explicacao:string,revisao:string,modulo:string}>
     */
    private function questoesIniciais(?array $prova): array
    {
        $digitado = old('questoes');
        if (is_array($digitado) && $digitado !== []) {
            $lista = [];
            foreach (array_values($digitado) as $q) {
                $q = is_array($q) ? $q : [];
                $alternativas = is_array($q['alternativas'] ?? null) ? array_values($q['alternativas']) : ['', '', '', ''];
                $lista[] = [
                    'id' => is_string($q['id'] ?? null) ? $q['id'] : '',
                    'enunciado' => is_string($q['enunciado'] ?? null) ? $q['enunciado'] : '',
                    'alternativas' => array_map(static fn (mixed $a): string => is_string($a) ? $a : '', $alternativas),
                    'correta' => is_numeric($q['correta'] ?? null) ? (int) $q['correta'] : 0,
                    'explicacao' => is_string($q['explicacao'] ?? null) ? $q['explicacao'] : '',
                    'revisao' => is_string($q['revisao'] ?? null) ? $q['revisao'] : '',
                    'modulo' => is_string($q['modulo'] ?? null) ? $q['modulo'] : '',
                ];
            }

            return $lista;
        }

        if ($prova !== null && $prova['questions'] !== []) {
            return array_map(static fn (array $q): array => [
                'id' => $q['id'],
                'enunciado' => $q['questionText'],
                'alternativas' => $q['options'],
                'correta' => $q['correctOptionIndex'],
                'explicacao' => $q['explanation'] ?? '',
                'revisao' => $q['reviewMessage'] ?? '',
                'modulo' => $q['recommendedModule'] ?? '',
            ], $prova['questions']);
        }

        return [['id' => '', 'enunciado' => '', 'alternativas' => ['', '', '', ''], 'correta' => 0, 'explicacao' => '', 'revisao' => '', 'modulo' => '']];
    }

    /**
     * Grava uma avaliação, nova ou editada.
     *
     * Quem grava é o `LearningService::createQuiz`, o mesmo da API — que agora
     * recusa gabarito impossível e não aceita id de questão de outra prova. A
     * conferência feita AQUI serve para dizer o problema da questão N em
     * português; o invariante mora no serviço.
     *
     * O curso é o do ENDEREÇO. O id da prova só vale se ela é deste curso.
     */
    public function salvarAvaliacao(Request $request, string $cursoRef, ?string $quizId = null): RedirectResponse
    {
        abort_unless(config('features.quizSimples') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $existente = null;
        if ($quizId !== null) {
            $existente = $this->aprendizagem->avaliacaoParaEditar($meu['id'], $quizId);
            abort_if($existente === null, 404);
        }

        $titulo = $request->string('titulo')->trim()->toString();
        $brutas = $request->input('questoes');
        $brutas = is_array($brutas) ? array_values($brutas) : [];

        $erros = [];
        if ($titulo === '' || mb_strlen($titulo) > 200) {
            $erros['titulo'] = $titulo === '' ? 'Dê um título à avaliação.' : 'O título pode ter até 200 caracteres.';
        }
        if ($brutas === []) {
            $erros['questoes'] = 'Uma avaliação precisa de pelo menos uma questão.';
        }
        if (count($brutas) > 100) {
            $erros['questoes'] = 'No máximo 100 questões por avaliação.';
        }

        // O que a questão tinha de `allowRetry` antes: a prova do aluno é
        // corrigida no fim e o campo deixou de ser usado, mas o que já estava
        // gravado não é jogado fora por quem edita outra coisa.
        $reentrada = [];
        foreach ($existente['questions'] ?? [] as $antiga) {
            $reentrada[$antiga['id']] = $antiga['allowRetry'];
        }

        $questoes = [];
        foreach ($brutas as $i => $bruta) {
            $bruta = is_array($bruta) ? $bruta : [];
            $enunciado = is_string($bruta['enunciado'] ?? null) ? trim($bruta['enunciado']) : '';
            $digitadas = is_array($bruta['alternativas'] ?? null) ? array_values($bruta['alternativas']) : [];
            $alternativas = array_map(static fn (mixed $a): string => is_string($a) ? $a : '', $digitadas);

            // Vazias do FIM não contam; a do MEIO é problema (desloca o gabarito).
            while ($alternativas !== [] && trim((string) end($alternativas)) === '') {
                array_pop($alternativas);
            }

            $correta = is_numeric($bruta['correta'] ?? null) ? (int) $bruta['correta'] : -1;
            $problema = QuestaoDeProva::problema($enunciado, $alternativas, $correta);

            if ($problema === null) {
                if (mb_strlen($enunciado) > 2000) {
                    $problema = 'O enunciado pode ter até 2000 caracteres.';
                } elseif (collect($alternativas)->contains(static fn (string $a): bool => mb_strlen($a) > 500)) {
                    $problema = 'Cada alternativa pode ter até 500 caracteres.';
                }
            }
            if ($problema !== null) {
                $erros['questoes.'.$i] = 'Questão '.($i + 1).': '.$problema;

                continue;
            }

            $idPedido = is_string($bruta['id'] ?? null) && $bruta['id'] !== '' ? $bruta['id'] : null;
            $texto = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;

            $questoes[] = array_filter([
                'id' => $idPedido,
                'questionText' => $enunciado,
                'options' => array_map(static fn (string $a): string => trim($a), $alternativas),
                'correctOptionIndex' => $correta,
                'explanation' => $texto($bruta['explicacao'] ?? null),
                'reviewMessage' => $texto($bruta['revisao'] ?? null),
                'recommendedModule' => $texto($bruta['modulo'] ?? null),
                'allowRetry' => $idPedido !== null ? ($reentrada[$idPedido] ?? true) : true,
            ], static fn (mixed $v): bool => $v !== null);
        }

        if ($erros !== []) {
            return back()->withInput()->withErrors($erros);
        }

        try {
            $salva = $this->aprendizagem->createQuiz(array_filter([
                'id' => $existente['id'] ?? null,
                'courseId' => $meu['id'],
                'title' => $titulo,
                'questions' => $questoes,
            ], static fn (mixed $v): bool => $v !== null), $instrutor);
        } catch (ApiException $e) {
            return back()->withInput()->withErrors(['questoes' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            $existente === null ? 'Criação de Avaliação' : 'Alteração de Avaliação',
            'Avaliação "'.$titulo.'" ('.(is_string($salva['id'] ?? null) ? $salva['id'] : '?').') do curso '.$meu['id']
                .($existente === null ? ' criada.' : ' alterada.'),
        );

        return redirect('/inst/curso/'.$meu['ref'].'/avaliacoes')->with(
            'avisoDoCurso',
            $existente === null ? 'Avaliação criada.' : 'Avaliação salva.',
        );
    }

    /**
     * Tira a avaliação do ar. As respostas dos alunos continuam registradas
     * (ADR 12) — `LearningService::deleteQuiz` inativa só a prova.
     */
    public function removerAvaliacao(Request $request, string $cursoRef, string $quizId): RedirectResponse
    {
        abort_unless(config('features.quizSimples') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        // A prova tem de ser DESTE curso: o `deleteQuiz` da API confere o dono
        // do curso da prova, mas o endereço também tem de dizer a verdade.
        $prova = $this->aprendizagem->avaliacaoParaEditar($meu['id'], $quizId);
        abort_if($prova === null, 404);

        try {
            $this->aprendizagem->deleteQuiz($quizId, $instrutor, 'Retirada pelo instrutor.');
        } catch (ApiException $e) {
            return redirect('/inst/curso/'.$meu['ref'].'/avaliacoes')->withErrors(['avaliacoes' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Inativação de Avaliação',
            'Avaliação "'.$prova['title'].'" ('.$quizId.') do curso '.$meu['id'].' retirada (respostas preservadas).',
            'WARNING',
        );

        return redirect('/inst/curso/'.$meu['ref'].'/avaliacoes')
            ->with('avisoDoCurso', 'Avaliação retirada. As respostas dos alunos continuam registradas.');
    }

    /**
     * Seções do instrutor que existem no sistema mas estão DESLIGADAS: o
     * servidor atende o endereço e diz isso, em vez de o React desenhar o
     * mesmo aviso. Com a flag LIGADA responde 501 — a tela ainda não existe —,
     * e o `SecoesDesligadasDoInstrutorTest` falha antes, apontando qual
     * construir. É a mesma decisão tomada para o aluno em 21/10.
     */
    public function secaoDesligada(Request $request, string $cursoRef, string $flag, string $titulo, string $explicacao): View|RedirectResponse
    {
        abort_if(config('features.'.$flag) === true, 501);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        return view('instrutor.secao-desligada', [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'titulo' => $titulo,
            'explicacao' => $explicacao,
        ]);
    }

    /**
     * `/inst/curso/{ref}/alunos` — quem está cursando, e os pedidos de matrícula.
     *
     * A aba do painel React era, na prática, vazia: com a aprovação automática
     * ligada ela só mostrava um aviso, embora o texto prometesse "acompanhamento
     * de turmas". Aqui ela mostra a turma de verdade — com o que o banco sabe, e
     * sem nenhum dos números que o painel antigo inventava.
     */
    public function alunos(Request $request, string $cursoRef): View|RedirectResponse
    {
        abort_unless(config('features.dadosGerenciais') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef, '/alunos');
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $automatica = config('features.aprovacaoAutomaticaMatricula') === true;

        return view('instrutor.alunos', $this->painel->alunosDoCurso($meu['id']) + [
            'curso' => $meu['curso'],
            'ref' => $meu['ref'],
            'automatica' => $automatica,
            'pedidos' => $automatica ? [] : $this->painel->pedidosPendentes($meu['id']),
        ]);
    }

    /**
     * Aprovar ou reprovar um pedido de matrícula.
     *
     * Quem decide é o `EnrollmentService::updateAdmissionStatus`, o mesmo da
     * API: ele confere a posse do curso, matricula na MESMA transação e, desde
     * 21/10, recusa aprovar matrícula em curso com a vigência vencida.
     *
     * O pedido tem de ser DESTE curso: o endereço diz qual é, e um id de pedido
     * de outro curso não se decide por aqui.
     */
    public function decidirPedido(Request $request, string $cursoRef, string $pedidoId): RedirectResponse
    {
        abort_unless(config('features.dadosGerenciais') === true, 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $meu = $this->meuCurso($instrutor, $cursoRef);
        if ($meu instanceof RedirectResponse) {
            return $meu;
        }

        $dados = $request->validate(['decisao' => ['required', 'in:approved,rejected']]);
        $decisao = $dados['decisao'] === 'approved' ? 'approved' : 'rejected';

        $pendentes = array_column($this->painel->pedidosPendentes($meu['id']), null, 'id');
        abort_unless(isset($pendentes[$pedidoId]), 404);

        try {
            $this->matriculas->updateAdmissionStatus($pedidoId, $decisao, $instrutor);
        } catch (ApiException $e) {
            return redirect('/inst/curso/'.$meu['ref'].'/alunos')->withErrors(['pedidos' => $e->getMessage()]);
        }

        $this->audit->log(
            $request,
            'Aprovação de Solicitação',
            'Matrícula '.$pedidoId.' ('.$pendentes[$pedidoId]['aluno'].') marcada como "'.$decisao.'".',
        );

        return redirect('/inst/curso/'.$meu['ref'].'/alunos')->with(
            'avisoDoCurso',
            $decisao === 'approved'
                ? 'Matrícula de '.$pendentes[$pedidoId]['aluno'].' aprovada.'
                : 'Matrícula de '.$pendentes[$pedidoId]['aluno'].' reprovada.',
        );
    }

    /** Campo de texto em branco é ausência de valor, não string vazia. */
    private static function vazioVirouNulo(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }
}
