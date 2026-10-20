<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Lesson;
use App\Services\CatalogService;
use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use App\Services\CourseService;
use App\Services\EnrollmentService;
use App\Services\MessagingService;
use App\Support\ConteudoDaAula;
use App\Support\CourseAccess;
use App\Support\ProgressoDoAluno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Área do aluno em Blade — telas 3, 4 e 5 do redesenho (plano 18, fase M3).
 *
 * Nenhuma regra de negócio mora aqui. Matrícula, progresso e o que cada papel
 * pode ver continuam nos services, que são os mesmos que a API usa: migrar a
 * tela não pode mudar quem alcança o quê (invariante 5 do plano 16).
 *
 * Por isso a lista de cursos sai de `CourseService::listCoursesFor`, e não de
 * um `Course::query()` escrito aqui: aquele método já aplica a escada de
 * visibilidade e já remove o material de quem não pertence ao curso. Um
 * atalho neste controller reabriria as duas portas de uma vez.
 */
final class AreaDoAlunoController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $matriculas,
        private readonly CourseService $cursos,
        private readonly CertificateService $certificados,
        private readonly CertificatePdfService $pdf,
        private readonly MessagingService $mensagens,
        private readonly CatalogService $catalogo,
    ) {}

    /**
     * Painel inicial do aluno — tela 3.
     *
     * O que a skill removeu desta tela: foto e ícone de perfil, o texto de
     * apoio sob a saudação, os indicadores "Curso ativo" e "Presença média", a
     * descrição do curso, as abas de estado, o bloco "Meu progresso", o
     * "Início da Jornada" e o catálogo de disciplinas disponíveis.
     *
     * Duas dessas remoções levavam função junto, e por decisão de 19/10 a
     * função **muda de lugar em vez de sair**:
     *
     * - matrícula em outro curso, que vivia no catálogo removido, passa a ser
     *   o item "Catálogo" do menu lateral;
     * - "Cursos concluídos" deixa de ser seção da página e vira submenu.
     */
    public function painel(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $matricula = $this->matriculaDe($aluno);
        $porId = $this->cursosPorId($aluno);

        $emAndamento = [];
        foreach ($this->idsEmAndamento($matricula) as $id) {
            $curso = $porId[$id] ?? null;
            if ($curso !== null) {
                $emAndamento[] = $this->comProgresso($curso, $aluno);
            }
        }

        return view('aluno.painel', [
            'emAndamento' => $emAndamento,
            'concluidos' => $this->concluidos($matricula, $porId, $aluno),
        ]);
    }

    /**
     * Certificados do aluno.
     *
     * Duas listas numa página só, e não três abas como no React: "Disponíveis"
     * e "Em andamento" são curtas, e esconder uma atrás de um controle só
     * acrescenta um clique. A terceira aba era **Validação** — o mesmo
     * autenticador da página pública `/certificados`, reimplementado dentro do
     * painel. A função fica, apontando para onde ela já existe: duas cópias do
     * mesmo formulário é o tipo de duplicata que diverge no dia em que uma
     * delas muda.
     *
     * A frequência aparece AQUI, e é o lugar onde ela explica alguma coisa —
     * saiu das telas de curso e de aula por decisão da skill, mas é o número
     * que libera o documento.
     *
     * Quem calcula é o `CertificateService`, o mesmo que emite. A tela não
     * refaz a conta: tê-la em dois lugares é como a tela passa a dizer 68%
     * enquanto a emissão recusa por 64%.
     */
    public function certificados(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        // `listCertificates` com papel de aluno devolve só as linhas dele.
        $emitidos = $this->certificados->listCertificates($aluno, 0, 100)['items'];

        $matricula = $this->matriculaDe($aluno);
        $porId = $this->cursosPorId($aluno);
        $comCertificado = [];
        foreach ($emitidos as $cert) {
            if (is_string($cert['courseId'] ?? null)) {
                $comCertificado[$cert['courseId']] = true;
            }
        }

        $emAndamento = [];
        foreach ($this->idsEmAndamento($matricula) as $id) {
            $curso = $porId[$id] ?? null;
            if ($curso === null || isset($comCertificado[$id])) {
                continue;
            }

            $emAndamento[] = [
                'curso' => $curso,
                'situacao' => $this->certificados->situacaoParaCertificado($id, $aluno['sub']),
            ];
        }

        return view('aluno.certificados', [
            'concluidos' => $this->concluidos($matricula, $porId, $aluno),
            'emitidos' => $emitidos,
            'emAndamento' => $emAndamento,
            'cargaPorCurso' => array_map(
                static fn (array $c): ?int => is_numeric($c['cargaHoraria'] ?? null) ? (int) $c['cargaHoraria'] : null,
                $porId,
            ),
        ]);
    }

    /**
     * Biblioteca: os materiais complementares, para baixar ou abrir.
     *
     * Responde 404 com `materiaisComplementares` desligado, como a API — o item
     * some do menu nesse caso, e o endereço digitado não abre uma tela vazia.
     */
    public function biblioteca(Request $request): View
    {
        abort_unless(config('features.materiaisComplementares') === true, 404);

        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        return view('aluno.biblioteca', [
            'itens' => $this->catalogo->listLibraryItemsFor($aluno['role']),
        ]);
    }

    /**
     * Ajuda: as dúvidas frequentes, e para onde ir quando elas não bastam.
     *
     * O conteúdo é o MESMO do `/duvidas` público, que a coordenação edita no
     * admin. A aba do React tinha seis perguntas escritas no código, e quatro
     * das respostas descreviam o que não existe: presença contada por
     * "confirmação de leitura", arquivamento preventivo do curso, uma
     * "política de saída de 5 dias", a aba de mensagens (fechada ao aluno) e
     * aulas ao vivo gravadas em 24 horas. Com uma fonte só, o que a escola
     * corrige no admin vale para as duas telas.
     */
    public function ajuda(): View
    {
        return view('aluno.ajuda');
    }

    /**
     * O PDF do certificado, pelo mesmo serviço que a API usa.
     *
     * Duas portas para o mesmo documento, e a diferença é só o
     * `Content-Disposition`: baixar salva o arquivo, ver abre no leitor do
     * navegador. No React havia um terceiro caminho — um "Visualizar" que
     * desenhava o certificado em HTML, uma SEGUNDA versão do mesmo documento,
     * que podia divergir do papel que sai impresso. Aqui as duas portas levam
     * ao mesmo PDF.
     *
     * Quem decide se esta pessoa pode é o serviço, que já recusa certificado de
     * outro aluno.
     */
    public function pdfDoCertificado(Request $request, string $certificadoId): Response
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $resultado = $this->pdf->renderPdf($certificadoId, $aluno);
        $inline = $request->query('modo') === 'ver';

        return response($resultado['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$resultado['filename'].'"',
        ]);
    }

    /**
     * Suporte pedagógico — a porta que a tela 5 deixou sem destino.
     *
     * ## O defeito que esta tela corrige
     *
     * O cartão "Suporte Pedagógico" da aula ENVIAVA uma mensagem ao gestor
     * (`POST /api/dms`, flag `mensagensDiretas`, ligada). Quando a skill o tirou
     * da aula, o item do menu passou a apontar para `/aluno/mensagens` — que é a
     * tela de LER a conversa, e essa depende também da flag `forum`, desligada.
     * O aluno clicava e lia "esta seção não está disponível": o único jeito de
     * pedir ajuda tinha sumido, o contrário da decisão de 19/10 ("tirar do
     * lugar sem tirar a função").
     *
     * Aqui a função volta, com o MESMO serviço e o MESMO formato: quando o
     * aluno vem de uma aula, a mensagem leva `[Aula: título]` na frente, como
     * o React fazia — é por isso que o gestor sabe de que aula se trata.
     *
     * ## O que esta tela NÃO promete
     *
     * Ler a resposta continua dependendo do canal de mensagens, que hoje está
     * fechado para o aluno (`forum: false`). A tela diz que a mensagem chega ao
     * gestor — e não que a resposta aparece aqui, porque não aparece.
     */
    public function suporte(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        return view('aluno.suporte', [
            'concluidos' => $this->concluidos($this->matriculaDe($aluno), $this->cursosPorId($aluno), $aluno),
            'aula' => $this->aulaDoAssunto($request->query('aula'), $aluno),
            'ligado' => config('features.mensagensDiretas') === true,
        ]);
    }

    public function enviarSuporte(Request $request): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        // A rota de API é guardada pela flag no middleware; a porta web tem de
        // guardar também, ou viraria o jeito de contornar o desligamento.
        abort_unless(config('features.mensagensDiretas') === true, 404);

        $dados = $request->validate([
            'texto' => ['required', 'string', 'min:1', 'max:2000'],
            'aula' => ['nullable', 'string', 'max:191'],
        ]);

        $texto = trim((string) $dados['texto']);
        $aula = $this->aulaDoAssunto($dados['aula'] ?? null, $aluno);

        $this->mensagens->createDirectMessage([
            'studentUserId' => null,
            // Mesmo formato do `comAssuntoDaAula` do React.
            'text' => $aula === null ? $texto : '[Aula: '.trim($aula['title']).'] '.$texto,
        ], $aluno);

        $destino = '/aluno/suporte'.($aula === null ? '' : '?aula='.urlencode($aula['id']));

        return redirect($destino)->with('suporteEnviado', true);
    }

    /**
     * A aula citada no assunto — só se ela for de um curso desta pessoa.
     *
     * O id vem da URL, e sem esta conferência qualquer aluno poderia pôr no
     * assunto o título de uma aula de curso alheio — pouco grave, mas é o tipo
     * de vazamento pequeno que ninguém procura.
     *
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return array{id:string,title:string}|null
     */
    private function aulaDoAssunto(mixed $aulaId, array $aluno): ?array
    {
        if (! is_string($aulaId) || $aulaId === '') {
            return null;
        }

        $aula = Lesson::query()->find($aulaId);
        if ($aula === null || ! in_array($aula->courseId, CourseAccess::accessibleCourseIds($aluno), true)) {
            return null;
        }

        return ['id' => (string) $aula->id, 'title' => (string) $aula->title];
    }

    /**
     * Navegação do curso — tela 4.
     *
     * O que a skill removeu: a descrição abaixo da imagem, a frequência e o
     * "frequência cumprida", o nome do gestor/instrutor e o indicador "Online".
     *
     * A frequência é **registro acadêmico** (ADR 12) e continua sendo gravada e
     * usada para emitir certificado — o que saiu é a exibição nesta tela. Ela
     * segue visível em Certificados, que é onde ela explica alguma coisa.
     *
     * `cursoRef` aceita slug atual, slug aposentado e id (ADR 13). Quando o
     * valor não é o endereço de hoje, redireciona para o canônico em vez de
     * responder no endereço antigo: assim o link velho continua funcionando sem
     * se propagar de tela em tela.
     */
    public function curso(Request $request, string $cursoRef): View|RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $resolvido = $this->cursos->resolverCurso($cursoRef, $aluno['role']);

        $matricula = $this->matriculaDe($aluno);
        $porId = $this->cursosPorId($aluno);
        $curso = $porId[$resolvido['id']] ?? null;

        /*
         * Esta tela é "o MEU curso", e não a ficha de um curso qualquer do
         * catálogo. `listCoursesFor` devolve todo curso publicado (com o
         * material removido para quem não pertence), então sem esta conferência
         * a tela abriria para um curso em que o aluno não está matriculado —
         * com a lista de aulas clicável e nenhum conteúdo atrás.
         *
         * A ficha de quem ainda não se matriculou é outra tela, `/aluno/catalogo`.
         */
        $meus = array_merge(
            $this->idsEmAndamento($matricula),
            array_values(array_filter(
                is_array($matricula['completedCourseIds'] ?? null) ? $matricula['completedCourseIds'] : [],
                static fn (mixed $id): bool => is_string($id),
            )),
        );

        abort_if($curso === null || ! in_array($resolvido['id'], $meus, true), 404);

        /*
         * A troca pelo endereço de hoje vem DEPOIS da conferência de matrícula.
         * Antes dela, pedir um curso alheio pelo id respondia com um redirecionamento
         * para o slug canônico — e o 404 só chegava no segundo pedido. O 404
         * continuava certo, mas o primeiro passo já contava algo sobre um curso
         * que não é daquela pessoa.
         */
        if (! $resolvido['canonico'] && $resolvido['slug'] !== '') {
            return redirect('/aluno/curso/'.$resolvido['slug']);
        }

        $curso = $this->comProgresso($curso, $aluno);

        return view('aluno.curso', [
            'curso' => $curso,
            'aulas' => $this->aulasEmOrdem($curso, $aluno),
            'concluidos' => $this->concluidos($matricula, $porId, $aluno),
        ]);
    }

    /**
     * Aulas na ordem da grade, com o estado de conclusão de cada uma.
     *
     * Pela ORDEM (`order`), nunca pela ordem de chegada do banco: foi assim que
     * "Iniciar primeira aula" chegou a abrir uma aula do meio no React.
     *
     * @param  array<string, mixed>  $curso
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return list<array<string, mixed>>
     */
    private function aulasEmOrdem(array $curso, array $aluno): array
    {
        $concluidas = $this->aulasConcluidasDoRegistro($curso['id'], $aluno);
        $aulas = is_array($curso['lessons'] ?? null) ? $curso['lessons'] : [];

        /** @var list<array<string, mixed>> $lista */
        $lista = [];

        foreach ($aulas as $aula) {
            if (! is_array($aula)) {
                continue;
            }

            /** @var array<string, mixed> $comEstado */
            $comEstado = $aula;
            $comEstado['concluida'] = in_array($comEstado['id'] ?? null, $concluidas, true);
            $lista[] = $comEstado;
        }

        // `order` vem do contrato da API como inteiro, mas é lido de um array de
        // `mixed`: aula sem ordem (ou com ordem que não é número) vai para o
        // começo em vez de derrubar a tela.
        $ordem = static function (array $aula): int {
            $valor = $aula['order'] ?? null;

            return is_numeric($valor) ? (int) $valor : 0;
        };

        usort($lista, static fn (array $a, array $b): int => $ordem($a) <=> $ordem($b));

        return $lista;
    }

    /**
     * Página da aula — tela 5.
     *
     * O layout aprovado é o B: título da aula **acima** do vídeo, vídeo em
     * destaque, conteúdo escrito abaixo em largura de leitura, e a navegação
     * das aulas recolhida no menu lateral — não na área principal, que fica só
     * para o conteúdo.
     *
     * No lugar de "Aula X de Y" aparece o nome do curso, por decisão da skill:
     * a posição na grade a pessoa vê na lista; o que ela precisa saber ali é
     * onde está.
     *
     * Saíram: frequência, "frequência cumprida", o ícone decorativo do título,
     * o selo "Online" e a duração em minutos.
     */
    public function aula(Request $request, string $cursoRef, string $aulaId): View|RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $resolvido = $this->cursos->resolverCurso($cursoRef, $aluno['role']);
        $matricula = $this->matriculaDe($aluno);
        $porId = $this->cursosPorId($aluno);
        $curso = $porId[$resolvido['id']] ?? null;

        abort_if(
            $curso === null || ! in_array($resolvido['id'], $this->idsDoAluno($matricula), true),
            404,
        );

        if (! $resolvido['canonico'] && $resolvido['slug'] !== '') {
            return redirect('/aluno/curso/'.$resolvido['slug'].'/aula/'.$aulaId);
        }

        $aulas = $this->aulasEmOrdem($curso, $aluno);
        $posicao = null;
        foreach ($aulas as $i => $aula) {
            if (($aula['id'] ?? null) === $aulaId) {
                $posicao = $i;
                break;
            }
        }

        // Aula que não é deste curso não é "aula vazia": é endereço que não
        // existe. Responder 200 com a moldura e nada dentro esconderia um link
        // quebrado — inclusive um link nosso, escrito errado.
        abort_if($posicao === null, 404);

        $aula = $aulas[$posicao];

        return view('aluno.aula', [
            'curso' => $this->comProgresso($curso, $aluno),
            'aulas' => $aulas,
            'aula' => $aula,
            'blocos' => ConteudoDaAula::analisar(is_string($aula['content'] ?? null) ? $aula['content'] : null)['blocos'],
            'anterior' => $aulas[$posicao - 1] ?? null,
            'proxima' => $aulas[$posicao + 1] ?? null,
            'concluidos' => $this->concluidos($matricula, $porId, $aluno),
        ]);
    }

    /**
     * Catálogo do aluno — a nova casa da matrícula.
     *
     * Ela vivia no painel, na seção "Disciplinas acadêmicas disponíveis", que a
     * skill removeu. Decisão de 19/10: tirar do lugar sem tirar a função.
     *
     * Mostra o que o aluno **ainda não tem**. Curso em que ele já está não é
     * oferta: continuar listando-o faria o cartão prometer uma matrícula que o
     * servidor recusaria.
     */
    public function catalogo(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $matricula = $this->matriculaDe($aluno);
        $meus = $this->idsDoAluno($matricula);

        $disponiveis = [];
        foreach ($this->cursosPorId($aluno) as $id => $curso) {
            if (! in_array($id, $meus, true)) {
                $disponiveis[] = $curso;
            }
        }

        return view('aluno.catalogo', [
            'cursos' => $disponiveis,
            'concluidos' => $this->concluidos($matricula, $this->cursosPorId($aluno), $aluno),
            'podeMatricular' => $this->podeMatricular($matricula),
        ]);
    }

    /** Ficha do curso no catálogo: o que ele é, antes de a pessoa se matricular. */
    public function fichaDoCatalogo(Request $request, string $cursoRef): View|RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $resolvido = $this->cursos->resolverCurso($cursoRef, $aluno['role']);
        $curso = $this->cursosPorId($aluno)[$resolvido['id']] ?? null;
        abort_if($curso === null, 404);

        $matricula = $this->matriculaDe($aluno);

        // Já é dele: a ficha do catálogo não serve para nada, e a tela do curso
        // sim. Mandar para lá é o que a pessoa queria ao clicar.
        if (in_array($resolvido['id'], $this->idsDoAluno($matricula), true)) {
            return redirect('/aluno/curso/'.($resolvido['slug'] !== '' ? $resolvido['slug'] : $resolvido['id']));
        }

        if (! $resolvido['canonico'] && $resolvido['slug'] !== '') {
            return redirect('/aluno/catalogo/'.$resolvido['slug']);
        }

        return view('aluno.catalogo-ficha', [
            'curso' => $curso,
            'aulas' => $this->aulasEmOrdem($curso, $aluno),
            'concluidos' => $this->concluidos($matricula, $this->cursosPorId($aluno), $aluno),
            'podeMatricular' => $this->podeMatricular($matricula),
        ]);
    }

    /**
     * Efetiva a matrícula.
     *
     * Quem decide se pode é o `EnrollmentService` — penalidade por desistência,
     * matrícula concorrente e duplicidade são regra de negócio dele, e
     * reescrevê-las aqui criaria uma segunda verdade sobre quem pode estudar o
     * quê. O controller só pergunta e traduz a resposta para a tela.
     *
     * O "Termo de Ciência" é validado NO SERVIDOR (`accepted`). No React ele só
     * desabilitava o botão — e botão desabilitado não é regra, é sugestão.
     */
    public function matricular(Request $request, string $cursoRef): RedirectResponse
    {
        /** @var array{sub:string,name:string,role:string} $aluno */
        $aluno = $request->attributes->get('auth_user');

        $request->validate(
            ['ciente' => ['accepted']],
            ['ciente.accepted' => 'É preciso confirmar que leu as regras de matrícula e certificação.'],
        );

        $resolvido = $this->cursos->resolverCurso($cursoRef, $aluno['role']);
        abort_if(! isset($this->cursosPorId($aluno)[$resolvido['id']]), 404);

        try {
            $this->matriculas->selfEnroll($resolvido['id'], $aluno);
        } catch (ApiException $erro) {
            return back()->withErrors(['matricula' => $erro->getMessage()]);
        }

        $destino = $resolvido['slug'] !== '' ? $resolvido['slug'] : $resolvido['id'];

        return redirect('/aluno/curso/'.$destino)
            ->with('aviso', 'Matrícula realizada. Você já pode iniciar seus estudos.');
    }

    /**
     * Todos os cursos que o aluno tem: em andamento e concluídos.
     *
     * @param  array<string, mixed>  $matricula
     * @return list<string>
     */
    private function idsDoAluno(array $matricula): array
    {
        $concluidos = is_array($matricula['completedCourseIds'] ?? null) ? $matricula['completedCourseIds'] : [];

        return array_values(array_unique(array_merge(
            $this->idsEmAndamento($matricula),
            array_values(array_filter($concluidos, static fn (mixed $id): bool => is_string($id))),
        )));
    }

    /**
     * O aluno pode assumir mais um curso agora?
     *
     * Espelha a leitura do painel React: sem curso ativo, pode; com curso
     * ativo, só quando a coordenação concedeu matrícula concorrente. Isto é
     * **só para a tela** — quem recusa de verdade é o service, e é por isso que
     * a tentativa continua indo até ele mesmo quando esta conta diz que não.
     *
     * @param  array<string, mixed>  $matricula
     */
    private function podeMatricular(array $matricula): bool
    {
        if ($this->idsEmAndamento($matricula) === []) {
            return true;
        }

        return config('features.matriculasMultiplas') === true
            && ($matricula['canMultiEnroll'] ?? false) === true;
    }

    /**
     * A matrícula deste aluno, já estreitada para um mapa.
     *
     * `getEnrollments` devolve um mapa de `mixed` (ele serve também ao admin,
     * que recebe todas). Sem estreitar aqui, cada leitura de campo mais adiante
     * seria sobre `mixed` — e é assim que um `completedCourseIds` que mudou de
     * forma vira erro longe da causa.
     *
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return array<string, mixed>
     */
    private function matriculaDe(array $aluno): array
    {
        $linha = $this->matriculas->getEnrollments($aluno)[$aluno['sub']] ?? [];

        return is_array($linha) ? $linha : [];
    }

    /**
     * Cursos que o aluno pode ver, indexados por id.
     *
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return array<string, array<string, mixed>>
     */
    private function cursosPorId(array $aluno): array
    {
        $porId = [];

        foreach ($this->cursos->listCoursesFor($aluno) as $curso) {
            if (is_string($curso['id'] ?? null)) {
                $porId[$curso['id']] = $curso;
            }
        }

        return $porId;
    }

    /**
     * Cursos em andamento: o da matrícula principal e os concorrentes, quando a
     * coordenação concedeu matrícula múltipla.
     *
     * @param  array<string, mixed>  $matricula
     * @return list<string>
     */
    private function idsEmAndamento(array $matricula): array
    {
        $extras = is_array($matricula['extraCourseIds'] ?? null) ? $matricula['extraCourseIds'] : [];
        $ids = array_merge(
            is_string($matricula['enrolledCourseId'] ?? null) ? [$matricula['enrolledCourseId']] : [],
            array_values(array_filter($extras, static fn (mixed $id): bool => is_string($id))),
        );

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @param  array<string, array<string, mixed>>  $porId
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return list<array<string, mixed>>
     */
    private function concluidos(array $matricula, array $porId, array $aluno): array
    {
        $concluidos = [];
        $ids = is_array($matricula['completedCourseIds'] ?? null) ? $matricula['completedCourseIds'] : [];

        foreach ($ids as $id) {
            if (is_string($id) && isset($porId[$id])) {
                $concluidos[] = $this->comProgresso($porId[$id], $aluno);
            }
        }

        return $concluidos;
    }

    /**
     * Acrescenta ao curso o que a tela mostra ao lado dele: quantas aulas foram
     * concluídas, de quantas, e o percentual da barra.
     *
     * O percentual NÃO vira texto na tela — a skill proíbe mostrar o número
     * duas vezes, e o texto "X de Y aulas concluídas" é o que ela manda exibir.
     * Ele existe só para a largura da barra e para o `aria-valuenow`.
     *
     * @param  array<string, mixed>  $curso
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return array<string, mixed>
     */
    private function comProgresso(array $curso, array $aluno): array
    {
        $aulas = is_array($curso['lessons'] ?? null) ? $curso['lessons'] : [];
        $idsDasAulas = array_values(array_map(
            static fn (mixed $aula): mixed => is_array($aula) ? ($aula['id'] ?? null) : null,
            $aulas,
        ));

        $concluidas = $this->aulasConcluidasDoRegistro($curso['id'], $aluno);

        return $curso + [
            'aulasConcluidas' => ProgressoDoAluno::contarValidas($concluidas, $idsDasAulas),
            'aulasNoCurso' => count($idsDasAulas),
            'percentual' => ProgressoDoAluno::percentual($concluidas, $idsDasAulas),
        ];
    }

    /**
     * Ids das aulas que o aluno marcou como concluídas naquele curso.
     *
     * `getProgress` é chamado uma vez por tela e memorizado: ele consulta o
     * banco, e chamá-lo por curso traria de volta o N+1 que o próprio service
     * evita ao devolver tudo de uma vez.
     *
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return list<mixed>
     */
    private function aulasConcluidasDoRegistro(mixed $courseId, array $aluno): array
    {
        $this->registros ??= $this->matriculas->getProgress(null, $aluno);

        foreach ($this->registros as $registro) {
            if (($registro['courseId'] ?? null) !== $courseId) {
                continue;
            }

            $concluidas = $registro['completedLessons'] ?? null;

            // `array_values` porque o contrato aqui é uma LISTA: o JSON do banco
            // pode chegar com chaves salteadas depois de uma remoção, e quem lê
            // percorre por posição.
            return is_array($concluidas) ? array_values($concluidas) : [];
        }

        return [];
    }

    /** @var array<int, array<string, mixed>>|null */
    private ?array $registros = null;
}
