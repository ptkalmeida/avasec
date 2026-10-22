<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Models\User;
use App\Support\ProgressoDoAluno;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que a tela "Alunos" do administrador mostra: a lista com filtros e a ficha.
 *
 * Somente leitura. Cadastro, redefinição de senha, matrícula múltipla e
 * inativação passam pelos mesmos services da API.
 *
 * ## O que o painel React mostrava e NÃO veio
 *
 * A tabela de alunos do painel era, em boa parte, inventada no navegador:
 *
 * - **RA, turma e polo**: o RA saía de uma conta sobre as letras do nome; turma
 *   era "2025.2" e polo "Online" para todos; três alunos de demonstração tinham
 *   linhas escritas no código.
 * - **Último acesso, "Ausentes > 7d" e "Risco de evasão"**: o banco não guarda
 *   acesso. O texto "Hoje, às 10:15" era fixo, e o risco era calculado lendo
 *   esse texto — todo aluno sem matrícula virava "Crítico".
 * - **Status da matrícula, status da conta, progresso manual e pendências**:
 *   gravados em `useState`, no navegador. Escolher "Bloqueada" não bloqueava
 *   ninguém, e recarregar a página desfazia tudo.
 * - **"Enviar notificação" e "Copiar link mágico"**: a notificação só mostrava
 *   o aviso de sucesso, e o link tinha um token escrito no código.
 *
 * Fica o que o banco sabe: matrículas, progresso, certificados, situação real
 * da conta e a permissão de matrícula múltipla.
 */
final class AlunosDoAdmin
{
    public const POR_PAGINA = 25;

    /**
     * @param  array{busca?:string,curso?:string,matricula?:string,conta?:string,ordem?:string}  $filtros
     * @return array{total:int,paginas:int,linhas:list<array{id:string,nome:string,email:?string,conta:string,cursos:list<string>,concluidos:int,progresso:int,multipla:bool}>}
     */
    public function listar(array $filtros, int $pagina): array
    {
        $consulta = $this->filtrada($filtros);

        $total = (clone $consulta)->count();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));

        $ordem = $filtros['ordem'] ?? 'nome';
        match ($ordem) {
            'nome-desc' => $consulta->orderByDesc('name'),
            'recentes' => $consulta->orderByDesc('createdAt'),
            default => $consulta->orderBy('name'),
        };

        $alunos = $consulta->orderBy('id')
            ->skip(($pagina - 1) * self::POR_PAGINA)->take(self::POR_PAGINA)
            ->get(['id', 'name', 'email', 'status']);

        $ids = [];
        foreach ($alunos as $aluno) {
            $ids[] = (string) $aluno->id;
        }

        $matriculas = $ids === [] ? collect() : StudentEnrollment::query()->whereIn('userId', $ids)->get()->keyBy('userId');
        $titulos = $this->titulosDosCursos();
        $aulasPorCurso = $this->aulasPorCurso();

        $percentuais = [];
        if ($ids !== []) {
            foreach (StudentProgress::query()->whereIn('userId', $ids)->get(['userId', 'courseId', 'completedLessons']) as $linha) {
                $feitas = $linha->completedLessons;
                $percentuais[(string) $linha->userId][] = ProgressoDoAluno::percentual(
                    is_array($feitas) ? array_values($feitas) : [],
                    $aulasPorCurso[(string) $linha->courseId] ?? [],
                );
            }
        }

        $linhas = [];
        foreach ($alunos as $aluno) {
            $id = (string) $aluno->id;
            $matricula = $matriculas->get($id);
            $dele = $percentuais[$id] ?? [];

            $linhas[] = [
                'id' => $id,
                'nome' => (string) $aluno->name,
                'email' => is_string($aluno->email) && $aluno->email !== '' ? $aluno->email : null,
                'conta' => (string) $aluno->status,
                'cursos' => $matricula === null ? [] : array_map(
                    static fn (string $cursoId): string => $titulos[$cursoId] ?? $cursoId,
                    $this->cursosAtivos($matricula),
                ),
                'concluidos' => $matricula === null ? 0 : count($this->ids($matricula->completedCourseIds)),
                'progresso' => $dele === [] ? 0 : (int) round(array_sum($dele) / count($dele)),
                'multipla' => $matricula !== null && $matricula->canMultiEnroll === true,
            ];
        }

        return ['total' => $total, 'paginas' => $paginas, 'linhas' => $linhas];
    }

    /**
     * Os números dos cartões do topo, sobre TODOS os alunos (não só a busca).
     *
     * @return array{total:int,comMatricula:int,semMatricula:int}
     */
    public function resumo(): array
    {
        $total = User::query()->where('role', 'student')->count();
        $com = $this->comMatricula(User::query()->where('role', 'student'))->count();

        return ['total' => $total, 'comMatricula' => $com, 'semMatricula' => $total - $com];
    }

    /**
     * Os cursos que a lista oferece como filtro: os que têm aluno matriculado
     * ou concluinte, e os do catálogo.
     *
     * @return list<array{id:string,titulo:string}>
     */
    public function cursosParaFiltro(): array
    {
        $lista = [];
        foreach (Course::query()->orderBy('title')->get(['id', 'title']) as $curso) {
            $lista[] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->title];
        }

        return $lista;
    }

    /**
     * A ficha de um aluno, ou null se não existir (ou estiver inativado).
     *
     * @return array{id:string,nome:string,nomeSocial:?string,email:?string,cpf:?string,celular:?string,municipio:?string,uf:?string,area:?string,cadastro:?string,conta:string,multipla:bool,matriculas:list<array{curso:string,tipo:string,progresso:int,aulasFeitas:int,aulas:int,certificado:bool}>,concluidos:list<string>,certificados:list<array{curso:string,emitidoEm:?string}>}|null
     */
    public function ficha(string $id): ?array
    {
        $aluno = User::query()->where('role', 'student')->find($id);
        if ($aluno === null) {
            return null;
        }

        $matricula = StudentEnrollment::query()->find($id);
        $titulos = $this->titulosDosCursos();
        $aulasPorCurso = $this->aulasPorCurso();

        $progresso = [];
        foreach (StudentProgress::query()->where('userId', $id)->get(['courseId', 'completedLessons']) as $linha) {
            $feitas = $linha->completedLessons;
            $progresso[(string) $linha->courseId] = is_array($feitas) ? array_values($feitas) : [];
        }

        $certificados = [];
        $comCertificado = [];
        foreach (Certificate::query()->where('userId', $id)->orderBy('issueDate')->get(['courseId', 'courseTitle', 'issueDate']) as $c) {
            $comCertificado[(string) $c->courseId] = true;
            $certificados[] = [
                'curso' => is_string($c->courseTitle) && $c->courseTitle !== '' ? $c->courseTitle : ($titulos[(string) $c->courseId] ?? (string) $c->courseId),
                'emitidoEm' => is_string($c->issueDate) && $c->issueDate !== '' ? $c->issueDate : null,
            ];
        }

        $matriculas = [];
        if ($matricula !== null) {
            $principal = is_string($matricula->enrolledCourseId) ? $matricula->enrolledCourseId : '';
            foreach ($this->cursosAtivos($matricula) as $cursoId) {
                $aulas = $aulasPorCurso[$cursoId] ?? [];
                $feitas = $progresso[$cursoId] ?? [];
                $matriculas[] = [
                    'curso' => $titulos[$cursoId] ?? $cursoId,
                    'tipo' => $cursoId === $principal ? 'Principal' : 'Concorrente',
                    'progresso' => ProgressoDoAluno::percentual($feitas, $aulas),
                    'aulasFeitas' => ProgressoDoAluno::contarValidas($feitas, $aulas),
                    'aulas' => count($aulas),
                    'certificado' => isset($comCertificado[$cursoId]),
                ];
            }
        }

        $concluidos = [];
        if ($matricula !== null) {
            foreach ($this->ids($matricula->completedCourseIds) as $cursoId) {
                $concluidos[] = $titulos[$cursoId] ?? $cursoId;
            }
        }

        $cpf = preg_replace('/\D/', '', (string) $aluno->cpf);

        return [
            'id' => (string) $aluno->id,
            'nome' => (string) $aluno->name,
            'nomeSocial' => $this->texto($aluno->nomeSocial),
            'email' => $this->texto($aluno->email),
            'cpf' => is_string($cpf) && strlen($cpf) === 11 ? substr($cpf, 0, 3).'.***.***-'.substr($cpf, 9, 2) : null,
            'celular' => $this->texto($aluno->celular),
            'municipio' => $this->texto($aluno->municipio),
            'uf' => $this->texto($aluno->uf),
            'area' => $this->texto($aluno->areaInteresse),
            'cadastro' => $this->texto($aluno->dataCadastro),
            'conta' => (string) $aluno->status,
            'multipla' => $matricula !== null && $matricula->canMultiEnroll === true,
            'matriculas' => $matriculas,
            'concluidos' => $concluidos,
            'certificados' => $certificados,
        ];
    }

    /**
     * @param  array{busca?:string,curso?:string,matricula?:string,conta?:string,ordem?:string}  $filtros
     * @return Builder<User>
     */
    private function filtrada(array $filtros): Builder
    {
        $consulta = User::query()->where('role', 'student');

        $busca = trim($filtros['busca'] ?? '');
        if ($busca !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($busca, '\\%_');
            $consulta->where(static function (Builder $q) use ($literal): void {
                $q->where('name', 'like', '%'.$literal.'%')->orWhere('email', 'like', '%'.$literal.'%');
            });
        }

        $curso = trim($filtros['curso'] ?? '');
        if ($curso !== '') {
            $json = '"'.$curso.'"';
            $consulta->whereIn('id', StudentEnrollment::query()->select('userId')->where(static function (Builder $q) use ($curso, $json): void {
                $q->where('enrolledCourseId', $curso)->orWhere('extraCourseIds', 'like', '%'.$json.'%');
            }));
        }

        $matricula = $filtros['matricula'] ?? '';
        if ($matricula === 'com') {
            $this->comMatricula($consulta);
        } elseif ($matricula === 'sem') {
            $consulta->whereNotIn('id', $this->comMatricula(User::query()->where('role', 'student'))->select('id'));
        } elseif ($matricula === 'concluiu') {
            $consulta->whereIn('id', StudentEnrollment::query()->select('userId')
                ->whereNotNull('completedCourseIds')->whereRaw('JSON_LENGTH(completedCourseIds) > 0'));
        }

        $conta = $filtros['conta'] ?? '';
        if (in_array($conta, ['active', 'blocked', 'pending_confirmation'], true)) {
            $consulta->where('status', $conta);
        }

        return $consulta;
    }

    /**
     * Quem está matriculado agora: curso principal ou algum concorrente.
     *
     * @param  Builder<User>  $consulta
     * @return Builder<User>
     */
    private function comMatricula(Builder $consulta): Builder
    {
        return $consulta->whereIn('id', StudentEnrollment::query()->select('userId')->where(static function (Builder $q): void {
            $q->where(static function (Builder $p): void {
                $p->whereNotNull('enrolledCourseId')->where('enrolledCourseId', '!=', '');
            })->orWhere(static function (Builder $e): void {
                $e->whereNotNull('extraCourseIds')->whereRaw('JSON_LENGTH(extraCourseIds) > 0');
            });
        }));
    }

    /**
     * Os cursos em que o aluno está agora: principal primeiro, depois os concorrentes.
     *
     * @return list<string>
     */
    private function cursosAtivos(StudentEnrollment $matricula): array
    {
        $cursos = [];
        if (is_string($matricula->enrolledCourseId) && $matricula->enrolledCourseId !== '') {
            $cursos[] = $matricula->enrolledCourseId;
        }
        foreach ($this->ids($matricula->extraCourseIds) as $extra) {
            $cursos[] = $extra;
        }

        return array_values(array_unique($cursos));
    }

    /**
     * @return list<string>
     */
    private function ids(mixed $valor): array
    {
        $lista = [];
        foreach (is_array($valor) ? $valor : [] as $item) {
            if (is_string($item) && $item !== '') {
                $lista[] = $item;
            }
        }

        return array_values(array_unique($lista));
    }

    /** @return array<string,string> */
    private function titulosDosCursos(): array
    {
        $mapa = [];
        foreach (Course::query()->withTrashed()->get(['id', 'title']) as $curso) {
            $mapa[(string) $curso->id] = (string) $curso->title;
        }

        return $mapa;
    }

    /** @return array<string,list<string>> */
    private function aulasPorCurso(): array
    {
        $mapa = [];
        foreach (Lesson::query()->get(['id', 'courseId']) as $aula) {
            $mapa[(string) $aula->courseId][] = (string) $aula->id;
        }

        return $mapa;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
