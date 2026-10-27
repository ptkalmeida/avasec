<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Support\Fuso;
use App\Support\ProgressoDoAluno;
use App\Support\Visibilidade;
use Throwable;

/**
 * Os números do Dashboard e dos cinco relatórios do painel administrativo.
 *
 * Somente leitura. Tradução do que o `AdminDashboard.tsx` calculava no
 * navegador a partir de listas inteiras (alunos, cursos, progresso,
 * matrículas, certificados) — aqui sai de consultas, e as listas longas vêm
 * paginadas.
 *
 * ## O que MUDOU em relação ao painel React, e por quê
 *
 * O painel contava com o que o navegador tinha; o servidor conta com o que o
 * banco sabe. Onde as duas leituras divergiram, vale a do banco:
 *
 * - **Matrícula é "principal + concorrentes"** (`curso_matriculado_id` e
 *   `cursos_extras_ids`), a mesma leitura do painel do instrutor. O React olhava só
 *   o principal, e uma aluna matriculada só num curso concorrente aparecia com
 *   zero matrículas.
 * - **Concluintes** vêm de `cursos_concluidos_ids`, a coluna que registra a
 *   conclusão, e não de "todas as aulas marcadas".
 * - **Progresso** conta só as aulas que ainda existem no curso
 *   (`ProgressoDoAluno`), como a tela do aluno.
 * - **Sem "Último acesso" e sem selos fixos.** O banco não guarda último
 *   acesso, e a coluna dizia "Sem acesso" para todos; "Regular", "Ativo",
 *   "Ativa" e "Publicado" eram texto fixo, igual em toda linha. Onde há um dado
 *   real (situação da conta, visibilidade do curso, certificado), ele aparece.
 */
final class RelatoriosDoAdmin
{
    /** Linhas por página nas listas longas (alunos e matrículas). */
    public const POR_PAGINA = 50;

    /**
     * Os quatro números do topo e a contagem da equipe.
     *
     * @return array{alunos:int,cursos:int,cursosPublicados:int,matriculas:int,certificados:int,equipe:int}
     */
    public function visaoGeral(): array
    {
        $cursos = Curso::query()->count();
        $publicados = Curso::query()->where('status', Visibilidade::PUBLICADO)->count();

        return [
            'alunos' => Usuario::query()->where('papel', 'student')->count(),
            'cursos' => $cursos,
            'cursosPublicados' => $publicados,
            'matriculas' => array_sum(array_map('count', $this->cursosDeCadaAluno())),
            'certificados' => Certificado::query()->count(),
            'equipe' => Usuario::query()->where('papel', 'instructor')->count(),
        ];
    }

    /**
     * Alunos, do mais antigo ao mais novo cadastro, com as matrículas e o
     * progresso médio de cada um.
     *
     * @return array{total:int,certificados:int,linhas:list<array{nome:string,email:?string,matriculas:int,progresso:int,situacao:string}>}
     */
    public function alunos(int $pagina): array
    {
        $consulta = Usuario::query()->where('papel', 'student');
        $total = (clone $consulta)->count();

        $alunos = $consulta->orderBy('criado_em')->orderBy('id')
            ->skip(($pagina - 1) * self::POR_PAGINA)->take(self::POR_PAGINA)
            ->get(['id', 'nome', 'email', 'status']);

        $ids = [];
        foreach ($alunos as $aluno) {
            $ids[] = (string) $aluno->id;
        }
        $cursosDe = $this->cursosDeCadaAluno($ids);
        $aulasPorCurso = $this->aulasPorCurso();

        $progressoPorAluno = [];
        foreach (ProgressoAluno::query()->whereIn('usuario_id', $ids)->get(['usuario_id', 'curso_id', 'aulas_concluidas']) as $linha) {
            $progressoPorAluno[(string) $linha->usuario_id][] = ProgressoDoAluno::percentual(
                self::lista($linha->aulas_concluidas),
                $aulasPorCurso[(string) $linha->curso_id] ?? [],
            );
        }

        $linhas = [];
        foreach ($alunos as $aluno) {
            $id = (string) $aluno->id;
            $percentuais = $progressoPorAluno[$id] ?? [];

            $linhas[] = [
                'nome' => (string) $aluno->nome,
                'email' => is_string($aluno->email) && $aluno->email !== '' ? $aluno->email : null,
                'matriculas' => count($cursosDe[$id] ?? []),
                'progresso' => $percentuais === [] ? 0 : (int) round(array_sum($percentuais) / count($percentuais)),
                'situacao' => (string) $aluno->status,
            ];
        }

        return [
            'total' => $total,
            'certificados' => Certificado::query()->count(),
            'linhas' => $linhas,
        ];
    }

    /**
     * A equipe pedagógica: cursos vinculados e aulas de cada pessoa.
     *
     * @return list<array{nome:string,cursos:int,aulas:int,situacao:string}>
     */
    public function equipe(): array
    {
        $pessoas = Usuario::query()->where('papel', 'instructor')->orderBy('nome')->get(['id', 'nome', 'status']);

        $cursosPorPessoa = [];
        foreach (Curso::query()->whereNotNull('instrutor_id')->get(['id', 'instrutor_id']) as $curso) {
            $cursosPorPessoa[(string) $curso->instrutor_id][] = (string) $curso->id;
        }

        $aulasPorCurso = $this->aulasPorCurso();

        $linhas = [];
        foreach ($pessoas as $pessoa) {
            $cursos = $cursosPorPessoa[(string) $pessoa->id] ?? [];
            $aulas = 0;
            foreach ($cursos as $cursoId) {
                $aulas += count($aulasPorCurso[$cursoId] ?? []);
            }

            $linhas[] = [
                'nome' => (string) $pessoa->nome,
                'cursos' => count($cursos),
                'aulas' => $aulas,
                'situacao' => (string) $pessoa->status,
            ];
        }

        return $linhas;
    }

    /**
     * Os cursos, com aulas, matriculados, concluintes e progresso médio.
     *
     * @return list<array{titulo:string,categoria:?string,aulas:int,matriculados:int,concluintes:int,progresso:int,visibilidade:int}>
     */
    public function cursos(): array
    {
        $aulasPorCurso = $this->aulasPorCurso();

        $matriculados = [];
        foreach ($this->cursosDeCadaAluno() as $cursosDoAluno) {
            foreach ($cursosDoAluno as $cursoId) {
                $matriculados[$cursoId] = ($matriculados[$cursoId] ?? 0) + 1;
            }
        }

        $concluintes = [];
        foreach (Matricula::query()->get(['cursos_concluidos_ids']) as $matricula) {
            $unicos = [];
            foreach (self::lista($matricula->cursos_concluidos_ids) as $cursoId) {
                if (is_string($cursoId)) {
                    $unicos[$cursoId] = true;
                }
            }
            foreach (array_keys($unicos) as $cursoId) {
                $concluintes[$cursoId] = ($concluintes[$cursoId] ?? 0) + 1;
            }
        }

        $percentuais = [];
        foreach (ProgressoAluno::query()->get(['curso_id', 'aulas_concluidas']) as $linha) {
            $cursoId = (string) $linha->curso_id;
            $percentuais[$cursoId][] = ProgressoDoAluno::percentual(
                self::lista($linha->aulas_concluidas),
                $aulasPorCurso[$cursoId] ?? [],
            );
        }

        $linhas = [];
        foreach (Curso::query()->orderBy('titulo')->get(['id', 'titulo', 'categoria', 'status']) as $curso) {
            $id = (string) $curso->id;
            $dele = $percentuais[$id] ?? [];

            $linhas[] = [
                'titulo' => (string) $curso->titulo,
                'categoria' => is_string($curso->categoria) && $curso->categoria !== '' ? $curso->categoria : null,
                'aulas' => count($aulasPorCurso[$id] ?? []),
                'matriculados' => $matriculados[$id] ?? 0,
                'concluintes' => $concluintes[$id] ?? 0,
                'progresso' => $dele === [] ? 0 : (int) round(array_sum($dele) / count($dele)),
                'visibilidade' => is_numeric($curso->getAttribute('status')) ? (int) $curso->getAttribute('status') : Visibilidade::PUBLICADO,
            ];
        }

        return $linhas;
    }

    /**
     * Uma linha por matrícula (aluno × curso): principal e concorrentes.
     *
     * @return array{total:int,linhas:list<array{aluno:string,curso:string,matriculadoEm:?string,progresso:int,certificado:bool}>}
     */
    public function matriculas(int $pagina): array
    {
        $todas = [];
        foreach ($this->cursosDeCadaAluno() as $alunoId => $cursos) {
            foreach ($cursos as $cursoId) {
                $todas[] = [(string) $alunoId, $cursoId];
            }
        }

        $total = count($todas);
        $fatia = array_slice($todas, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);
        if ($fatia === []) {
            return ['total' => $total, 'linhas' => []];
        }

        $alunoIds = array_values(array_unique(array_column($fatia, 0)));
        $cursoIds = array_values(array_unique(array_column($fatia, 1)));

        $nomes = Usuario::query()->withTrashed()->whereIn('id', $alunoIds)->pluck('nome', 'id');
        $cursos = Curso::query()->withTrashed()->whereIn('id', $cursoIds)->pluck('titulo', 'id');
        $matriculas = Matricula::query()->whereIn('usuario_id', $alunoIds)->get(['usuario_id', 'nome_aluno', 'curso_matriculado_id', 'matriculado_em_texto'])->keyBy('usuario_id');
        $aulasPorCurso = $this->aulasPorCurso();

        $progresso = [];
        foreach (ProgressoAluno::query()->whereIn('usuario_id', $alunoIds)->whereIn('curso_id', $cursoIds)->get(['usuario_id', 'curso_id', 'aulas_concluidas']) as $linha) {
            $progresso[(string) $linha->usuario_id][(string) $linha->curso_id] = ProgressoDoAluno::percentual(
                self::lista($linha->aulas_concluidas),
                $aulasPorCurso[(string) $linha->curso_id] ?? [],
            );
        }

        $comCertificado = [];
        foreach (Certificado::query()->whereIn('usuario_id', $alunoIds)->whereIn('curso_id', $cursoIds)->get(['usuario_id', 'curso_id']) as $c) {
            $comCertificado[(string) $c->usuario_id][(string) $c->curso_id] = true;
        }

        $linhas = [];
        foreach ($fatia as [$alunoId, $cursoId]) {
            $matricula = $matriculas->get($alunoId);
            // A data só existe para a matrícula principal.
            $data = $matricula !== null && (string) $matricula->curso_matriculado_id === $cursoId
                ? $this->data($matricula->matriculado_em_texto)
                : null;

            $linhas[] = [
                'aluno' => $this->texto($nomes[$alunoId] ?? ($matricula !== null ? $matricula->nome_aluno : null), $alunoId),
                'curso' => $this->texto($cursos[$cursoId] ?? null, $cursoId),
                'matriculadoEm' => $data,
                'progresso' => $progresso[$alunoId][$cursoId] ?? 0,
                'certificado' => isset($comCertificado[$alunoId][$cursoId]),
            ];
        }

        return ['total' => $total, 'linhas' => $linhas];
    }

    /**
     * Os cursos em que cada aluno está matriculado agora: o principal e os
     * concorrentes. Conclusão é outra coluna e não entra.
     *
     * @param  list<string>|null  $alunoIds  null = todos
     * @return array<string,list<string>> usuario_id → ids de curso, sem repetir
     */
    private function cursosDeCadaAluno(?array $alunoIds = null): array
    {
        $consulta = Matricula::query();
        if ($alunoIds !== null) {
            $consulta->whereIn('usuario_id', $alunoIds);
        }

        $mapa = [];
        foreach ($consulta->get(['usuario_id', 'curso_matriculado_id', 'cursos_extras_ids']) as $matricula) {
            $cursos = [];
            if (is_string($matricula->curso_matriculado_id) && $matricula->curso_matriculado_id !== '') {
                $cursos[] = $matricula->curso_matriculado_id;
            }
            foreach (self::lista($matricula->cursos_extras_ids) as $extra) {
                if (is_string($extra) && $extra !== '') {
                    $cursos[] = $extra;
                }
            }

            $cursos = array_values(array_unique($cursos));
            if ($cursos !== []) {
                $mapa[(string) $matricula->usuario_id] = $cursos;
            }
        }

        return $mapa;
    }

    /**
     * Ids das aulas de cada curso.
     *
     * @return array<string,list<string>>
     */
    private function aulasPorCurso(): array
    {
        $mapa = [];
        foreach (Aula::query()->get(['id', 'curso_id']) as $aula) {
            $mapa[(string) $aula->curso_id][] = (string) $aula->id;
        }

        return $mapa;
    }

    /** @return list<mixed> */
    private static function lista(mixed $valor): array
    {
        return is_array($valor) ? array_values($valor) : [];
    }

    private function texto(mixed $valor, string $padrao): string
    {
        return is_string($valor) && $valor !== '' ? $valor : $padrao;
    }

    private function data(mixed $instante): ?string
    {
        if (! is_string($instante) || $instante === '') {
            return null;
        }

        try {
            return Fuso::local(new \DateTimeImmutable($instante))?->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }
}
