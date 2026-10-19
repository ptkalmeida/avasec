<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CourseService;
use App\Services\EnrollmentService;
use App\Support\ProgressoDoAluno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
