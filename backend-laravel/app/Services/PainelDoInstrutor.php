<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SolicitacaoIngresso;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Avaliacao;
use App\Models\TentativaAvaliacao;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Support\BusinessRules;
use App\Support\CanalDeMensagens;
use App\Support\Fuso;
use App\Support\InstructorScope;
use App\Support\ProgressoDoAluno;
use App\Support\SafeUrl;
use App\Support\VigenciaDoCurso;
use App\Support\Visibilidade;
use Carbon\CarbonInterface;

/**
 * O que o painel do instrutor precisa saber, calculado num lugar só.
 *
 * Nenhuma regra de negócio nova mora aqui: posse de curso continua vindo de
 * `InstructorScope` (FK `instrutor_id`, ADR 10), que é a mesma fonte que a API
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
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
     * @return list<array{id:string,slug:string|null,titulo:string,categoria:string|null,aulas:int,alunos:int,vigencia_ate:string|null,vigenciaVencida:bool,publicado:bool}>
     */
    public function cursos(array $instrutor): array
    {
        /*
         * Curso INATIVADO não entra, e isso é a mesma regra da API: quem sai
         * do ar sai de `/api/cursos`, e o painel React também não o mostrava.
         * Trazê-lo de volta aqui ressuscitaria na tela do instrutor o que a
         * coordenação tirou — os dois cursos inativados hoje são, de fato,
         * limpeza de dados de teste.
         *
         * O que fica em aberto, e está no plano 18: o instrutor cujo curso é
         * tirado do ar vê o curso sumir sem nenhuma explicação, embora o motivo
         * esteja gravado em `motivo_inativacao`.
         */
        $consulta = Curso::query();
        if ($instrutor['papel'] !== 'admin') {
            $consulta->whereIn('id', InstructorScope::courseIds($instrutor));
        }

        $cursos = $consulta->orderBy('titulo')->get();
        if ($cursos->isEmpty()) {
            return [];
        }

        $ids = $cursos->pluck('id')->all();
        $aulas = Aula::query()->whereIn('curso_id', $ids)
            ->selectRaw('curso_id, COUNT(*) as total')->groupBy('curso_id')
            ->pluck('total', 'curso_id');

        // Data pura compara com data pura, como TEXTO `Y-m-d`.
        $hoje = Fuso::agora()->format('Y-m-d');

        $lista = [];
        foreach ($cursos as $curso) {
            /*
             * `vigencia_ate` e `status` entraram por migration em laço (as de
             * 08/10 e a da escada de visibilidade), então a análise estática
             * não as enxerga como propriedade do modelo. `getAttribute` com
             * estreitamento diz a verdade sobre o que pode vir: nulo, quando a
             * coluna não foi preenchida.
             */
            $vencimento = $curso->getAttribute('vigencia_ate');
            $vencimento = $vencimento instanceof CarbonInterface ? $vencimento : null;
            $visibilidade = $curso->getAttribute('status');
            $vigencia = ['vigencia_ate' => $vencimento, 'vencimento_contrato_texto' => $curso->vencimento_contrato_texto];
            $lista[] = [
                'id' => (string) $curso->id,
                'slug' => is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : null,
                'titulo' => (string) $curso->titulo,
                'categoria' => is_string($curso->categoria) && $curso->categoria !== '' ? $curso->categoria : null,
                'aulas' => is_numeric($n = $aulas[$curso->id] ?? 0) ? (int) $n : 0,
                'alunos' => $this->quantosAlunos((string) $curso->id),
                'vigencia_ate' => VigenciaDoCurso::diaParaPessoa($vigencia),
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
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
     * @return array{curso:array<string, mixed>,aulas:int,avaliacoes:int,alunos:int,minimaEfetiva:int,minimaDoCurso:int|null,vigenciaVencida:bool,vigencia_ate:string|null,emite_certificado:bool,encontros:list<array{titulo:string,quando:string,link:string|null}>}
     */
    public function panoramaDoCurso(array $curso, array $instrutor): array
    {
        $id = is_string($curso['id'] ?? null) ? $curso['id'] : '';

        $avaliacoes = Avaliacao::query()->where('curso_id', $id);
        Visibilidade::aplicar($avaliacoes, 'student');

        $minimaDoCurso = is_numeric($curso['frequencia_minima'] ?? null) ? (int) $curso['frequencia_minima'] : null;

        return [
            'curso' => $curso,
            'aulas' => is_array($curso['aulas'] ?? null) ? count($curso['aulas']) : 0,
            'avaliacoes' => $avaliacoes->count(),
            'alunos' => $this->quantosAlunos($id),
            'minimaEfetiva' => BusinessRules::courseMinAttendance($minimaDoCurso),
            'minimaDoCurso' => $minimaDoCurso,
            'vigenciaVencida' => VigenciaDoCurso::vencida($curso),
            'vigencia_ate' => VigenciaDoCurso::diaParaPessoa($curso),
            // Fonte única: a coluna do curso, a mesma que a emissão usa
            // (`CertificateService::situacaoParaCertificado`) — decisão de 23/10/2026.
            'emite_certificado' => ($curso['emite_certificado'] ?? null) !== false,
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
        $sessoes = is_array($curso['sessoes_ao_vivo'] ?? null) ? $curso['sessoes_ao_vivo'] : [];

        $lista = [];
        foreach ($sessoes as $sessao) {
            if (! is_array($sessao)) {
                continue;
            }
            $link = SafeUrl::sanitize(is_string($sessao['link_reuniao'] ?? null) ? $sessao['link_reuniao'] : null);
            $lista[] = [
                'titulo' => is_string($sessao['titulo'] ?? null) ? $sessao['titulo'] : 'Encontro',
                // `agendado_para_texto` é texto local, já no fuso de exibição: não se
                // converte de novo (a armadilha está documentada em `Fuso`).
                'quando' => is_string($sessao['agendado_para_texto'] ?? null) ? $sessao['agendado_para_texto'] : '',
                'link' => $link,
            ];
        }

        usort($lista, static fn (array $a, array $b): int => strcmp($a['quando'], $b['quando']));

        return $lista;
    }

    /**
     * Os alunos do curso, como o instrutor os acompanha.
     *
     * Quem entra: os que estão CURSANDO — matrícula principal ou concorrente —,
     * que é o mesmo escopo de `InstructorScope::studentIds`. Quem já concluiu
     * não aparece com nome, e sim na contagem: ex-aluno não fica no alcance do
     * instrutor para sempre (a decisão está registrada no `InstructorScope`).
     *
     * Cada número vem de onde o resto do sistema o tira:
     *  - progresso: `ProgressoDoAluno`, que só conta aula que AINDA existe;
     *  - avaliações: uma tentativa aprovada basta, como o certificado;
     *  - certificado: a linha em `certificado`, que nunca é apagada (ADR 12).
     *
     * O painel React mostrava, na aba de mensagens, uma "presença" tirada de uma
     * tabela fixa por nome de aluno, e e-mail inventado a partir do nome. Nada
     * disso veio: aqui, só o que o banco sabe.
     *
     * @return array{cursando:list<array{id:string,nome:string,email:string|null,aulasFeitas:int,aulas:int,provasAprovadas:int,provas:int,certificado:bool}>,concluiram:int}
     */
    public function alunosDoCurso(string $curso_id): array
    {
        $idsDasAulas = array_values(Aula::query()->where('curso_id', $curso_id)->pluck('id')->all());
        $json = '"'.$curso_id.'"';

        $matriculas = Matricula::query()
            ->where(static function ($q) use ($curso_id, $json): void {
                $q->where('curso_matriculado_id', $curso_id)
                    ->orWhere('cursos_extras_ids', 'like', '%'.$json.'%');
            })
            ->get(['usuario_id', 'nome_aluno']);

        $concluiram = Matricula::query()
            ->where('cursos_concluidos_ids', 'like', '%'.$json.'%')
            ->count();

        $ids = $matriculas->pluck('usuario_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return ['cursando' => [], 'concluiram' => $concluiram];
        }

        $usuarios = Usuario::query()->whereIn('id', $ids)->get(['id', 'nome', 'email'])->keyBy('id');
        $progresso = ProgressoAluno::query()->where('curso_id', $curso_id)->whereIn('usuario_id', $ids)->get()->keyBy('usuario_id');

        $publicadas = Avaliacao::query()->where('curso_id', $curso_id);
        Visibilidade::aplicar($publicadas, 'student');
        $idsDasProvas = $publicadas->pluck('id')->all();

        $aprovadas = TentativaAvaliacao::query()
            ->where('curso_id', $curso_id)->where('aprovado', true)
            ->whereIn('avaliacao_id', $idsDasProvas)->whereIn('usuario_id', $ids)
            ->get(['usuario_id', 'avaliacao_id'])
            ->groupBy('usuario_id')
            ->map(static fn ($linhas): int => $linhas->pluck('avaliacao_id')->unique()->count());

        $comCertificado = Certificado::query()->where('curso_id', $curso_id)->whereIn('usuario_id', $ids)
            ->pluck('usuario_id')->flip();

        $lista = [];
        foreach ($matriculas as $m) {
            $uid = (string) $m->usuario_id;
            $usuario = $usuarios->get($uid);
            $feitas = $progresso->get($uid)?->aulas_concluidas;

            $lista[] = [
                'id' => $uid,
                'nome' => $usuario !== null ? (string) $usuario->nome : (string) $m->nome_aluno,
                'email' => $usuario !== null && is_string($usuario->email) && $usuario->email !== '' ? $usuario->email : null,
                'aulasFeitas' => ProgressoDoAluno::contarValidas(is_array($feitas) ? array_values($feitas) : [], $idsDasAulas),
                'aulas' => count($idsDasAulas),
                'provasAprovadas' => (int) ($aprovadas->get($uid) ?? 0),
                'provas' => count($idsDasProvas),
                'certificado' => $comCertificado->has($uid),
            ];
        }

        usort($lista, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return ['cursando' => $lista, 'concluiram' => $concluiram];
    }

    /**
     * Pedidos de matrícula aguardando decisão neste curso.
     *
     * Só existem com a aprovação automática DESLIGADA: com ela ligada — a
     * configuração de hoje — o pedido nasce aprovado. A tela decide mostrar a
     * fila pela REGRA, e não por a lista estar vazia.
     *
     * @return list<array{id:string,aluno:string,enviado_em:string}>
     */
    public function pedidosPendentes(string $curso_id): array
    {
        $lista = [];
        $pedidos = SolicitacaoIngresso::query()
            ->where('curso_id', $curso_id)->where('status', 'pending')
            ->orderBy('id')->get(['id', 'nome_aluno', 'enviado_em_texto']);

        foreach ($pedidos as $p) {
            $lista[] = [
                'id' => (string) $p->id,
                'aluno' => (string) $p->nome_aluno,
                'enviado_em' => (string) $p->enviado_em_texto,
            ];
        }

        return $lista;
    }

    /**
     * Quantos alunos estão neste curso: matriculados no principal, nos
     * concorrentes (`cursos_extras_ids`) ou que já o concluíram.
     *
     * A contagem é a mesma leitura que `EnrollmentService` faz das três
     * colunas; está aqui porque o painel precisa dela POR CURSO, e aquele
     * serviço responde por pessoa.
     */
    private function quantosAlunos(string $curso_id): int
    {
        $json = '"'.$curso_id.'"';

        return Matricula::query()
            ->where('curso_matriculado_id', $curso_id)
            ->orWhere('cursos_extras_ids', 'like', '%'.$json.'%')
            ->orWhere('cursos_concluidos_ids', 'like', '%'.$json.'%')
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
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
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
            $cursos = $instrutor['papel'] === 'admin' ? null : InstructorScope::courseIds($instrutor);
            $pendentes = SolicitacaoIngresso::query()->where('status', 'pending')
                ->when($cursos !== null, static fn ($q) => $q->whereIn('curso_id', $cursos ?? []))
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
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
     */
    public function quantasPendencias(array $instrutor): int
    {
        return array_sum(array_column($this->aFazer($instrutor), 'quantidade'));
    }
}
