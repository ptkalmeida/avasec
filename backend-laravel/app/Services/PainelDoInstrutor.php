<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdmissionRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\StudentEnrollment;
use App\Support\BusinessRules;
use App\Support\CanalDeMensagens;
use App\Support\Fuso;
use App\Support\InstructorScope;
use App\Support\SafeUrl;
use App\Support\VigenciaDoCurso;
use App\Support\Visibilidade;
use Carbon\CarbonInterface;

/**
 * O que o painel do instrutor precisa saber, calculado num lugar só.
 *
 * Nenhuma regra de negócio nova mora aqui: posse de curso continua vindo de
 * `InstructorScope` (FK `instructorId`, ADR 10), que é a mesma fonte que a API
 * usa. O que este serviço faz é reunir, para a tela, o que o React montava
 * filtrando listas já carregadas no navegador.
 */
final class PainelDoInstrutor
{
    public function __construct(private readonly CaixaDeMensagens $caixa) {}

    /**
     * Os cursos sob responsabilidade de quem está olhando.
     *
     * Admin vê todos, como em toda a API. Instrutor vê os seus — inclusive o
     * curso em rascunho ou restrito, porque é ele quem o prepara; a escada de
     * `Visibilidade` governa quem LÊ o conteúdo, não quem o gerencia.
     *
     * @param  array{sub:string,name:string,role:string}  $instrutor
     * @return list<array{id:string,slug:string|null,title:string,category:string|null,aulas:int,alunos:int,vigenciaAte:string|null,vigenciaVencida:bool,publicado:bool}>
     */
    public function cursos(array $instrutor): array
    {
        /*
         * Curso INATIVADO não entra, e isso é a mesma regra da API: quem sai
         * do ar sai de `/api/courses`, e o painel React também não o mostrava.
         * Trazê-lo de volta aqui ressuscitaria na tela do instrutor o que a
         * coordenação tirou — os dois cursos inativados hoje são, de fato,
         * limpeza de dados de teste.
         *
         * O que fica em aberto, e está no plano 18: o instrutor cujo curso é
         * tirado do ar vê o curso sumir sem nenhuma explicação, embora o motivo
         * esteja gravado em `motivoInativacao`.
         */
        $consulta = Course::query();
        if ($instrutor['role'] !== 'admin') {
            $consulta->whereIn('id', InstructorScope::courseIds($instrutor));
        }

        $cursos = $consulta->orderBy('title')->get();
        if ($cursos->isEmpty()) {
            return [];
        }

        $ids = $cursos->pluck('id')->all();
        $aulas = Lesson::query()->whereIn('courseId', $ids)
            ->selectRaw('courseId, COUNT(*) as total')->groupBy('courseId')
            ->pluck('total', 'courseId');

        // Data pura compara com data pura, como TEXTO `Y-m-d`.
        $hoje = Fuso::agora()->format('Y-m-d');

        $lista = [];
        foreach ($cursos as $curso) {
            /*
             * `vigenciaAte` e `status` entraram por migration em laço (as de
             * 08/10 e a da escada de visibilidade), então a análise estática
             * não as enxerga como propriedade do modelo. `getAttribute` com
             * estreitamento diz a verdade sobre o que pode vir: nulo, quando a
             * coluna não foi preenchida.
             */
            $vencimento = $curso->getAttribute('vigenciaAte');
            $vencimento = $vencimento instanceof CarbonInterface ? $vencimento : null;
            $visibilidade = $curso->getAttribute('status');
            $vigencia = ['vigenciaAte' => $vencimento, 'contractExpirationDate' => $curso->contractExpirationDate];
            $lista[] = [
                'id' => (string) $curso->id,
                'slug' => is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : null,
                'title' => (string) $curso->title,
                'category' => is_string($curso->category) && $curso->category !== '' ? $curso->category : null,
                'aulas' => is_numeric($n = $aulas[$curso->id] ?? 0) ? (int) $n : 0,
                'alunos' => $this->quantosAlunos((string) $curso->id),
                'vigenciaAte' => VigenciaDoCurso::diaParaPessoa($vigencia),
                // A pergunta tem uma resposta só, em `VigenciaDoCurso`.
                'vigenciaVencida' => VigenciaDoCurso::vencida($vigencia),
                'publicado' => is_numeric($visibilidade) && (int) $visibilidade === Visibilidade::PUBLICADO,
            ];
        }

        return $lista;
    }

    /**
     * O panorama de um curso, como a Gestão do Curso o mostra.
     *
     * Nada aqui é recalculado por conta própria: a frequência mínima efetiva
     * vem de `BusinessRules`, a vigência de `VigenciaDoCurso` e as contagens do
     * próprio banco. O painel React montava estes números filtrando listas já
     * carregadas no navegador, e por isso eles mudavam de valor conforme a
     * hidratação.
     *
     * @param  array<string, mixed>  $curso  Como `CourseService::getCourseById` o entrega.
     * @param  array{sub:string,name:string,role:string}  $instrutor
     * @return array{curso:array<string, mixed>,aulas:int,avaliacoes:int,alunos:int,minimaEfetiva:int,minimaDoCurso:int|null,vigenciaVencida:bool,vigenciaAte:string|null,emiteCertificado:bool,encontros:list<array{titulo:string,quando:string,link:string|null}>}
     */
    public function panoramaDoCurso(array $curso, array $instrutor): array
    {
        $id = is_string($curso['id'] ?? null) ? $curso['id'] : '';

        $avaliacoes = Quiz::query()->where('courseId', $id);
        Visibilidade::aplicar($avaliacoes, 'student');

        $minimaDoCurso = is_numeric($curso['minAttendance'] ?? null) ? (int) $curso['minAttendance'] : null;

        return [
            'curso' => $curso,
            'aulas' => is_array($curso['lessons'] ?? null) ? count($curso['lessons']) : 0,
            'avaliacoes' => $avaliacoes->count(),
            'alunos' => $this->quantosAlunos($id),
            'minimaEfetiva' => BusinessRules::courseMinAttendance($minimaDoCurso),
            'minimaDoCurso' => $minimaDoCurso,
            'vigenciaVencida' => VigenciaDoCurso::vencida($curso),
            'vigenciaAte' => VigenciaDoCurso::diaParaPessoa($curso),
            /*
             * `emiteCertificado` tem DUAS fontes no sistema: esta coluna e a
             * categoria do curso conter "Sem Certificado"
             * (`CertificateService::situacaoParaCertificado`). A tela mostra o
             * que a emissão de fato usa — as duas juntas —, para não prometer
             * certificado que não sai. A duplicação está sinalizada no plano 18.
             */
            'emiteCertificado' => ($curso['emiteCertificado'] ?? null) !== false
                && ! str_contains(is_string($curso['category'] ?? null) ? $curso['category'] : '', 'Sem Certificado'),
            'encontros' => $this->encontros($curso),
        ];
    }

    /**
     * Os encontros ao vivo agendados, do mais próximo ao mais distante.
     *
     * Só leitura nesta fatia. O botão "Acessar Sala" do painel React não veio:
     * ele abria a sala interna, que está desligada (`liveClassroom`), e o
     * clique não fazia nada. O link externo do encontro, quando existe, é o que
     * de fato leva a algum lugar.
     *
     * @param  array<string, mixed>  $curso
     * @return list<array{titulo:string,quando:string,link:string|null}>
     */
    private function encontros(array $curso): array
    {
        $sessoes = is_array($curso['liveSessions'] ?? null) ? $curso['liveSessions'] : [];

        $lista = [];
        foreach ($sessoes as $sessao) {
            if (! is_array($sessao)) {
                continue;
            }
            $link = SafeUrl::sanitize(is_string($sessao['meetingLink'] ?? null) ? $sessao['meetingLink'] : null);
            $lista[] = [
                'titulo' => is_string($sessao['title'] ?? null) ? $sessao['title'] : 'Encontro',
                // `scheduledAt` é texto local, já no fuso de exibição: não se
                // converte de novo (a armadilha está documentada em `Fuso`).
                'quando' => is_string($sessao['scheduledAt'] ?? null) ? $sessao['scheduledAt'] : '',
                'link' => $link,
            ];
        }

        usort($lista, static fn (array $a, array $b): int => strcmp($a['quando'], $b['quando']));

        return $lista;
    }

    /**
     * Quantos alunos estão neste curso: matriculados no principal, nos
     * concorrentes (`extraCourseIds`) ou que já o concluíram.
     *
     * A contagem é a mesma leitura que `EnrollmentService` faz das três
     * colunas; está aqui porque o painel precisa dela POR CURSO, e aquele
     * serviço responde por pessoa.
     */
    private function quantosAlunos(string $courseId): int
    {
        $json = '"'.$courseId.'"';

        return StudentEnrollment::query()
            ->where('enrolledCourseId', $courseId)
            ->orWhere('extraCourseIds', 'like', '%'.$json.'%')
            ->orWhere('completedCourseIds', 'like', '%'.$json.'%')
            ->count();
    }

    /**
     * A fila "A fazer": o trabalho diário, contado.
     *
     * Dois itens, e não três. O painel React já registrava a razão: avaliação
     * neste sistema é corrigida pelo servidor, então não há "nota a lançar".
     * Item com zero não entra — uma fila que diz "0 a corrigir" vira ruído
     * permanente e a pessoa para de ler a fila inteira.
     *
     * @param  array{sub:string,name:string,role:string}  $instrutor
     * @return list<array{id:string,quantidade:int,texto:string,rota:string}>
     */
    public function aFazer(array $instrutor): array
    {
        $fila = [];

        /*
         * Matrícula só espera aprovação quando a aprovação NÃO é automática.
         * Com `aprovacaoAutomaticaMatricula` ligada — que é hoje — a fila some
         * pela regra, e não por a lista estar vazia. O React semeava três
         * solicitações fictícias no navegador, e sem esta trava a fila
         * anunciava trabalho que não existia.
         *
         * Exercícios a corrigir ficam de fora enquanto
         * `atividadesPraticasAvancadas` estiver desligada: a API responde 404,
         * e contar trabalho que não se pode fazer é pior que não contar.
         */
        if (config('features.aprovacaoAutomaticaMatricula') !== true) {
            $cursos = $instrutor['role'] === 'admin' ? null : InstructorScope::courseIds($instrutor);
            $pendentes = AdmissionRequest::query()->where('status', 'pending')
                ->when($cursos !== null, static fn ($q) => $q->whereIn('courseId', $cursos ?? []))
                ->count();

            if ($pendentes > 0) {
                $fila[] = [
                    'id' => 'matriculas',
                    'quantidade' => $pendentes,
                    'texto' => $pendentes === 1
                        ? '1 matrícula aguardando aprovação'
                        : $pendentes.' matrículas aguardando aprovação',
                    'rota' => '/inst',
                ];
            }
        }

        /*
         * Aluno esperando resposta é trabalho diário, e era o que menos
         * aparecia: a conversa ficava numa aba que o gestor precisava abrir
         * para descobrir que havia alguém esperando.
         */
        if (CanalDeMensagens::abertoParaInstrutor()) {
            $aguardando = $this->caixa->quantasAguardam($instrutor);
            if ($aguardando > 0) {
                $fila[] = [
                    'id' => 'mensagens',
                    'quantidade' => $aguardando,
                    'texto' => $aguardando === 1
                        ? '1 aluno aguardando resposta'
                        : $aguardando.' alunos aguardando resposta',
                    'rota' => '/inst/mensagens',
                ];
            }
        }

        return $fila;
    }

    /**
     * Quantas pendências ao todo — o número que o menu mostra.
     *
     * @param  array{sub:string,name:string,role:string}  $instrutor
     */
    public function quantasPendencias(array $instrutor): int
    {
        return array_sum(array_column($this->aFazer($instrutor), 'quantidade'));
    }
}
