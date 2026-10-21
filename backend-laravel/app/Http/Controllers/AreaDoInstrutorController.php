<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuditLogger;
use App\Services\CaixaDeMensagens;
use App\Services\CourseService;
use App\Services\MessagingService;
use App\Services\PainelDoInstrutor;
use App\Support\CanalDeMensagens;
use App\Support\Fuso;
use App\Support\VigenciaDoCurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        private readonly CaixaDeMensagens $caixa,
        private readonly MessagingService $mensagens,
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

    /** Campo de texto em branco é ausência de valor, não string vazia. */
    private static function vazioVirouNulo(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }
}
