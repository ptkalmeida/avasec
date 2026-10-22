<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Models\User;
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
 * - **Matrícula é "principal + concorrentes"** (`enrolledCourseId` e
 *   `extraCourseIds`), a mesma leitura do painel do instrutor. O React olhava só
 *   o principal, e uma aluna matriculada só num curso concorrente aparecia com
 *   zero matrículas.
 * - **Concluintes** vêm de `completedCourseIds`, a coluna que registra a
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
        $cursos = Course::query()->count();
        $publicados = Course::query()->where('status', Visibilidade::PUBLICADO)->count();

        return [
            'alunos' => User::query()->where('role', 'student')->count(),
            'cursos' => $cursos,
            'cursosPublicados' => $publicados,
            'matriculas' => array_sum(array_map('count', $this->cursosDeCadaAluno())),
            'certificados' => Certificate::query()->count(),
            'equipe' => User::query()->where('role', 'instructor')->count(),
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
        $consulta = User::query()->where('role', 'student');
        $total = (clone $consulta)->count();

        $alunos = $consulta->orderBy('createdAt')->orderBy('id')
            ->skip(($pagina - 1) * self::POR_PAGINA)->take(self::POR_PAGINA)
            ->get(['id', 'name', 'email', 'status']);

        $ids = [];
        foreach ($alunos as $aluno) {
            $ids[] = (string) $aluno->id;
        }
        $cursosDe = $this->cursosDeCadaAluno($ids);
        $aulasPorCurso = $this->aulasPorCurso();

        $progressoPorAluno = [];
        foreach (StudentProgress::query()->whereIn('userId', $ids)->get(['userId', 'courseId', 'completedLessons']) as $linha) {
            $progressoPorAluno[(string) $linha->userId][] = ProgressoDoAluno::percentual(
                self::lista($linha->completedLessons),
                $aulasPorCurso[(string) $linha->courseId] ?? [],
            );
        }

        $linhas = [];
        foreach ($alunos as $aluno) {
            $id = (string) $aluno->id;
            $percentuais = $progressoPorAluno[$id] ?? [];

            $linhas[] = [
                'nome' => (string) $aluno->name,
                'email' => is_string($aluno->email) && $aluno->email !== '' ? $aluno->email : null,
                'matriculas' => count($cursosDe[$id] ?? []),
                'progresso' => $percentuais === [] ? 0 : (int) round(array_sum($percentuais) / count($percentuais)),
                'situacao' => (string) $aluno->status,
            ];
        }

        return [
            'total' => $total,
            'certificados' => Certificate::query()->count(),
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
        $pessoas = User::query()->where('role', 'instructor')->orderBy('name')->get(['id', 'name', 'status']);

        $cursosPorPessoa = [];
        foreach (Course::query()->whereNotNull('instructorId')->get(['id', 'instructorId']) as $curso) {
            $cursosPorPessoa[(string) $curso->instructorId][] = (string) $curso->id;
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
                'nome' => (string) $pessoa->name,
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
        foreach (StudentEnrollment::query()->get(['completedCourseIds']) as $matricula) {
            $unicos = [];
            foreach (self::lista($matricula->completedCourseIds) as $cursoId) {
                if (is_string($cursoId)) {
                    $unicos[$cursoId] = true;
                }
            }
            foreach (array_keys($unicos) as $cursoId) {
                $concluintes[$cursoId] = ($concluintes[$cursoId] ?? 0) + 1;
            }
        }

        $percentuais = [];
        foreach (StudentProgress::query()->get(['courseId', 'completedLessons']) as $linha) {
            $cursoId = (string) $linha->courseId;
            $percentuais[$cursoId][] = ProgressoDoAluno::percentual(
                self::lista($linha->completedLessons),
                $aulasPorCurso[$cursoId] ?? [],
            );
        }

        $linhas = [];
        foreach (Course::query()->orderBy('title')->get(['id', 'title', 'category', 'status']) as $curso) {
            $id = (string) $curso->id;
            $dele = $percentuais[$id] ?? [];

            $linhas[] = [
                'titulo' => (string) $curso->title,
                'categoria' => is_string($curso->category) && $curso->category !== '' ? $curso->category : null,
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

        $nomes = User::query()->withTrashed()->whereIn('id', $alunoIds)->pluck('name', 'id');
        $cursos = Course::query()->withTrashed()->whereIn('id', $cursoIds)->pluck('title', 'id');
        $matriculas = StudentEnrollment::query()->whereIn('userId', $alunoIds)->get(['userId', 'studentName', 'enrolledCourseId', 'enrolledAt'])->keyBy('userId');
        $aulasPorCurso = $this->aulasPorCurso();

        $progresso = [];
        foreach (StudentProgress::query()->whereIn('userId', $alunoIds)->whereIn('courseId', $cursoIds)->get(['userId', 'courseId', 'completedLessons']) as $linha) {
            $progresso[(string) $linha->userId][(string) $linha->courseId] = ProgressoDoAluno::percentual(
                self::lista($linha->completedLessons),
                $aulasPorCurso[(string) $linha->courseId] ?? [],
            );
        }

        $comCertificado = [];
        foreach (Certificate::query()->whereIn('userId', $alunoIds)->whereIn('courseId', $cursoIds)->get(['userId', 'courseId']) as $c) {
            $comCertificado[(string) $c->userId][(string) $c->courseId] = true;
        }

        $linhas = [];
        foreach ($fatia as [$alunoId, $cursoId]) {
            $matricula = $matriculas->get($alunoId);
            // A data só existe para a matrícula principal.
            $data = $matricula !== null && (string) $matricula->enrolledCourseId === $cursoId
                ? $this->data($matricula->enrolledAt)
                : null;

            $linhas[] = [
                'aluno' => $this->texto($nomes[$alunoId] ?? ($matricula !== null ? $matricula->studentName : null), $alunoId),
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
     * @return array<string,list<string>> userId → ids de curso, sem repetir
     */
    private function cursosDeCadaAluno(?array $alunoIds = null): array
    {
        $consulta = StudentEnrollment::query();
        if ($alunoIds !== null) {
            $consulta->whereIn('userId', $alunoIds);
        }

        $mapa = [];
        foreach ($consulta->get(['userId', 'enrolledCourseId', 'extraCourseIds']) as $matricula) {
            $cursos = [];
            if (is_string($matricula->enrolledCourseId) && $matricula->enrolledCourseId !== '') {
                $cursos[] = $matricula->enrolledCourseId;
            }
            foreach (self::lista($matricula->extraCourseIds) as $extra) {
                if (is_string($extra) && $extra !== '') {
                    $cursos[] = $extra;
                }
            }

            $cursos = array_values(array_unique($cursos));
            if ($cursos !== []) {
                $mapa[(string) $matricula->userId] = $cursos;
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
        foreach (Lesson::query()->get(['id', 'courseId']) as $aula) {
            $mapa[(string) $aula->courseId][] = (string) $aula->id;
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
