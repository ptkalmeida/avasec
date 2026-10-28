<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aula;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
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
            'nome-desc' => $consulta->orderByDesc('nome'),
            'recentes' => $consulta->orderByDesc('criado_em'),
            default => $consulta->orderBy('nome'),
        };

        $alunos = $consulta->orderBy('id')
            ->skip(($pagina - 1) * self::POR_PAGINA)->take(self::POR_PAGINA)
            ->get(['id', 'nome', 'email', 'status']);

        $ids = [];
        foreach ($alunos as $aluno) {
            $ids[] = (string) $aluno->id;
        }

        $matriculas = $ids === [] ? collect() : Matricula::query()->whereIn('usuario_id', $ids)->get()->keyBy('usuario_id');
        $titulos = $this->titulosDosCursos();
        $aulasPorCurso = $this->aulasPorCurso();

        $percentuais = [];
        if ($ids !== []) {
            foreach (ProgressoAluno::query()->whereIn('usuario_id', $ids)->get(['usuario_id', 'curso_id', 'aulas_concluidas']) as $linha) {
                $feitas = $linha->aulas_concluidas;
                $percentuais[(string) $linha->usuario_id][] = ProgressoDoAluno::percentual(
                    is_array($feitas) ? array_values($feitas) : [],
                    $aulasPorCurso[(string) $linha->curso_id] ?? [],
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
                'nome' => (string) $aluno->nome,
                'email' => is_string($aluno->email) && $aluno->email !== '' ? $aluno->email : null,
                'conta' => (string) $aluno->status,
                'cursos' => $matricula === null ? [] : array_map(
                    static fn (string $cursoId): string => $titulos[$cursoId] ?? $cursoId,
                    $this->cursosAtivos($matricula),
                ),
                'concluidos' => $matricula === null ? 0 : count($this->ids($matricula->cursos_concluidos_ids)),
                'progresso' => $dele === [] ? 0 : (int) round(array_sum($dele) / count($dele)),
                'multipla' => $matricula !== null && $matricula->pode_multimatricula === true,
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
        $total = Usuario::query()->where('papel', 'student')->count();
        $com = $this->comMatricula(Usuario::query()->where('papel', 'student'))->count();

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
        foreach (Curso::query()->orderBy('titulo')->get(['id', 'titulo']) as $curso) {
            $lista[] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->titulo];
        }

        return $lista;
    }

    /**
     * A ficha de um aluno, ou null se não existir (ou estiver inativado).
     *
     * @return array{id:string,nome:string,nome_social:?string,email:?string,cpf:?string,celular:?string,municipio:?string,uf:?string,area:?string,cadastro:?string,conta:string,multipla:bool,matriculas:list<array{curso:string,tipo:string,progresso:int,aulasFeitas:int,aulas:int,certificado:bool}>,concluidos:list<string>,certificados:list<array{curso:string,emitidoEm:?string}>}|null
     */
    public function ficha(string $id): ?array
    {
        $aluno = Usuario::query()->where('papel', 'student')->find($id);
        if ($aluno === null) {
            return null;
        }

        $matricula = Matricula::query()->find($id);
        $titulos = $this->titulosDosCursos();
        $aulasPorCurso = $this->aulasPorCurso();

        $progresso = [];
        foreach (ProgressoAluno::query()->where('usuario_id', $id)->get(['curso_id', 'aulas_concluidas']) as $linha) {
            $feitas = $linha->aulas_concluidas;
            $progresso[(string) $linha->curso_id] = is_array($feitas) ? array_values($feitas) : [];
        }

        $certificados = [];
        $comCertificado = [];
        foreach (Certificado::query()->where('usuario_id', $id)->orderBy('data_emissao')->get(['curso_id', 'titulo_curso', 'data_emissao']) as $c) {
            $comCertificado[(string) $c->curso_id] = true;
            $certificados[] = [
                'curso' => is_string($c->titulo_curso) && $c->titulo_curso !== '' ? $c->titulo_curso : ($titulos[(string) $c->curso_id] ?? (string) $c->curso_id),
                'emitidoEm' => is_string($c->data_emissao) && $c->data_emissao !== '' ? $c->data_emissao : null,
            ];
        }

        $matriculas = [];
        if ($matricula !== null) {
            $principal = is_string($matricula->curso_matriculado_id) ? $matricula->curso_matriculado_id : '';
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
            foreach ($this->ids($matricula->cursos_concluidos_ids) as $cursoId) {
                $concluidos[] = $titulos[$cursoId] ?? $cursoId;
            }
        }

        $cpf = preg_replace('/\D/', '', (string) $aluno->cpf);

        return [
            'id' => (string) $aluno->id,
            'nome' => (string) $aluno->nome,
            'nome_social' => $this->texto($aluno->nome_social),
            'email' => $this->texto($aluno->email),
            'cpf' => is_string($cpf) && strlen($cpf) === 11 ? substr($cpf, 0, 3).'.***.***-'.substr($cpf, 9, 2) : null,
            'celular' => $this->texto($aluno->celular),
            'municipio' => $this->texto($aluno->municipio),
            'uf' => $this->texto($aluno->uf),
            'area' => $this->texto($aluno->area_interesse),
            'cadastro' => $this->texto($aluno->data_cadastro_texto),
            'conta' => (string) $aluno->status,
            'multipla' => $matricula !== null && $matricula->pode_multimatricula === true,
            'matriculas' => $matriculas,
            'concluidos' => $concluidos,
            'certificados' => $certificados,
        ];
    }

    /**
     * @param  array{busca?:string,curso?:string,matricula?:string,conta?:string,ordem?:string}  $filtros
     * @return Builder<Usuario>
     */
    private function filtrada(array $filtros): Builder
    {
        $consulta = Usuario::query()->where('papel', 'student');

        $busca = trim($filtros['busca'] ?? '');
        if ($busca !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($busca, '\\%_');
            $consulta->where(static function (Builder $q) use ($literal): void {
                $q->where('nome', 'like', '%'.$literal.'%')->orWhere('email', 'like', '%'.$literal.'%');
            });
        }

        $curso = trim($filtros['curso'] ?? '');
        if ($curso !== '') {
            $json = '"'.$curso.'"';
            $consulta->whereIn('id', Matricula::query()->select('usuario_id')->where(static function (Builder $q) use ($curso, $json): void {
                $q->where('curso_matriculado_id', $curso)->orWhere('cursos_extras_ids', 'like', '%'.$json.'%');
            }));
        }

        $matricula = $filtros['matricula'] ?? '';
        if ($matricula === 'com') {
            $this->comMatricula($consulta);
        } elseif ($matricula === 'sem') {
            $consulta->whereNotIn('id', $this->comMatricula(Usuario::query()->where('papel', 'student'))->select('id'));
        } elseif ($matricula === 'concluiu') {
            $consulta->whereIn('id', Matricula::query()->select('usuario_id')
                ->whereNotNull('cursos_concluidos_ids')->whereRaw('JSON_LENGTH(cursos_concluidos_ids) > 0'));
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
     * @param  Builder<Usuario>  $consulta
     * @return Builder<Usuario>
     */
    private function comMatricula(Builder $consulta): Builder
    {
        return $consulta->whereIn('id', Matricula::query()->select('usuario_id')->where(static function (Builder $q): void {
            $q->where(static function (Builder $p): void {
                $p->whereNotNull('curso_matriculado_id')->where('curso_matriculado_id', '!=', '');
            })->orWhere(static function (Builder $e): void {
                $e->whereNotNull('cursos_extras_ids')->whereRaw('JSON_LENGTH(cursos_extras_ids) > 0');
            });
        }));
    }

    /**
     * Os cursos em que o aluno está agora: principal primeiro, depois os concorrentes.
     *
     * @return list<string>
     */
    private function cursosAtivos(Matricula $matricula): array
    {
        $cursos = [];
        if (is_string($matricula->curso_matriculado_id) && $matricula->curso_matriculado_id !== '') {
            $cursos[] = $matricula->curso_matriculado_id;
        }
        foreach ($this->ids($matricula->cursos_extras_ids) as $extra) {
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
        foreach (Curso::query()->withTrashed()->get(['id', 'titulo']) as $curso) {
            $mapa[(string) $curso->id] = (string) $curso->titulo;
        }

        return $mapa;
    }

    /** @return array<string,list<string>> */
    private function aulasPorCurso(): array
    {
        $mapa = [];
        foreach (Aula::query()->get(['id', 'curso_id']) as $aula) {
            $mapa[(string) $aula->curso_id][] = (string) $aula->id;
        }

        return $mapa;
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
