<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use DateTimeInterface;

/**
 * As cinco bases de Dados Gerenciais (BI) em CSV.
 *
 * Tradução de `src/utils/managementExport.ts`: as MESMAS colunas, na mesma
 * ordem e com os mesmos valores, e o mesmo arquivo (`;` como separador, BOM no
 * início para o Excel abrir a acentuação). Quem alimenta Power BI ou Excel com
 * essas planilhas não pode ver o layout mudar por a tela ter mudado de
 * tecnologia — por isso há um teste que confere a saída linha a linha.
 *
 * A fonte é a mesma de antes: o `ExportService`, restrito a admin e auditado.
 *
 * ## Dois comportamentos preservados que merecem decisão
 *
 * - **A base de Matrículas só lê o curso principal e os concluídos.** Quem está
 *   matriculado apenas em curso concorrente (`extraCourseIds`) não aparece nela
 *   (hoje há um caso assim). O painel React já fazia assim; aqui fica igual, para
 *   não alterar uma planilha que alguém já consome, e fica registrado para o
 *   responsável decidir.
 * - **Base vazia não gera arquivo.** Sem registro nenhum, o painel antigo não
 *   baixava nada, em silêncio. Aqui o botão avisa que não há registros.
 */
final class BasesGerenciais
{
    /** Percentual mínimo de frequência quando o curso não define um (`DEFAULT_MIN_ATTENDANCE`). */
    private const FREQUENCIA_PADRAO = 70;

    /**
     * id da base → rótulo, descrição e nome do arquivo.
     *
     * @var array<string, array{rotulo:string,titulo:string,descricao:string,arquivo:string}>
     */
    public const BASES = [
        'alunos' => [
            'rotulo' => '1. Base de Alunos',
            'titulo' => 'Base de Alunos',
            'descricao' => 'Cadastro dos alunos: identificação, situação da conta, município, UF, área de interesse e data de cadastro.',
            'arquivo' => 'base_alunos.csv',
        ],
        'cursos' => [
            'rotulo' => '2. Base de Cursos',
            'titulo' => 'Base de Cursos',
            'descricao' => 'Catálogo de cursos: título, categoria, área temática, carga horária, modalidade, nível, responsável e percentual mínimo de frequência.',
            'arquivo' => 'base_cursos.csv',
        ],
        'matriculas' => [
            'rotulo' => '3. Base de Matrículas',
            'titulo' => 'Base de Matrículas',
            'descricao' => 'Matrículas ativas e concluídas, ligadas ao aluno e ao curso, com a situação do certificado.',
            'arquivo' => 'base_matriculas.csv',
        ],
        'progresso' => [
            'rotulo' => '4. Progresso por Aula',
            'titulo' => 'Progresso por Aula',
            'descricao' => 'Uma linha por aula de cada curso com progresso registrado: se o aluno já a concluiu ou não.',
            'arquivo' => 'base_progresso_modulo.csv',
        ],
        'certificados' => [
            'rotulo' => '5. Base de Certificados',
            'titulo' => 'Base de Certificados',
            'descricao' => 'Certificados emitidos, com o código de validação, a data de emissão e o percentual de frequência.',
            'arquivo' => 'base_certificados.csv',
        ],
    ];

    public function __construct(private readonly ExportService $exportacao) {}

    /**
     * @return list<array<string,mixed>>
     */
    public function linhas(string $base): array
    {
        return match ($base) {
            'alunos' => $this->alunos($this->exportacao->exportDataset('students')),
            'cursos' => $this->cursos($this->exportacao->exportDataset('courses')),
            'matriculas' => $this->matriculas(
                $this->exportacao->exportDataset('enrollments'),
                $this->exportacao->exportDataset('progress'),
                $this->exportacao->exportDataset('certificates'),
            ),
            'progresso' => $this->progresso($this->exportacao->exportDataset('progress'), $this->catalogo()),
            'certificados' => $this->certificados($this->exportacao->exportDataset('certificates')),
            default => [],
        };
    }

    /**
     * O CSV da base — com BOM, `;` e aspas só onde precisa, como o painel antigo.
     * Base sem registro devolve null (não há o que baixar).
     *
     * @param  array<int, array<string,mixed>>  $linhas
     */
    public function csv(array $linhas): ?string
    {
        if ($linhas === []) {
            return null;
        }

        $cabecalhos = array_keys($linhas[0]);
        $saida = [implode(';', $cabecalhos)];
        foreach ($linhas as $linha) {
            $campos = [];
            foreach ($cabecalhos as $cabecalho) {
                $campos[] = $this->campo($linha[$cabecalho] ?? null);
            }
            $saida[] = implode(';', $campos);
        }

        return "\u{FEFF}".implode("\n", $saida);
    }

    /**
     * @param  array<int, array<string,mixed>>  $alunos
     * @return list<array<string,mixed>>
     */
    private function alunos(array $alunos): array
    {
        return array_values(array_map(fn (array $u): array => [
            'id_aluno' => $u['id'] ?? null,
            'nome' => $u['name'] ?? null,
            'email' => $u['email'] ?? null,
            'status_conta' => $u['status'] ?? null,
            'municipio' => $u['municipio'] ?? '',
            'uf' => $u['uf'] ?? '',
            'area_de_interesse' => $u['areaInteresse'] ?? '',
            'data_de_cadastro' => $u['dataCadastro'] ?? '',
        ], $alunos));
    }

    /**
     * @param  array<int, array<string,mixed>>  $cursos
     * @return list<array<string,mixed>>
     */
    private function cursos(array $cursos): array
    {
        return array_values(array_map(fn (array $c): array => [
            'id_curso' => $c['id'] ?? null,
            'titulo' => $c['title'] ?? null,
            'categoria' => $c['category'] ?? null,
            'area_tematica' => $c['areaTematica'] ?? '',
            'carga_horaria' => $c['cargaHoraria'] ?? '',
            'modalidade' => $c['modalidade'] ?? '',
            'nivel' => $c['nivel'] ?? '',
            'professor_responsavel' => $c['instructorName'] ?? null,
            'emite_certificado' => ($c['emiteCertificado'] ?? null) !== false ? 'Sim' : 'Não',
            'percentual_minimo' => $c['minAttendance'] ?? self::FREQUENCIA_PADRAO,
            'status_do_curso' => $c['statusCurso'] ?? '',
        ], $cursos));
    }

    /**
     * @param  array<int, array<string,mixed>>  $matriculas
     * @param  array<int, array<string,mixed>>  $progresso
     * @param  array<int, array<string,mixed>>  $certificados
     * @return list<array<string,mixed>>
     */
    private function matriculas(array $matriculas, array $progresso, array $certificados): array
    {
        $linhas = [];

        foreach ($matriculas as $e) {
            $idDaMatricula = $e['id'] ?? $e['userId'] ?? null;
            $principal = $e['enrolledCourseId'] ?? null;

            if (is_string($principal) && $principal !== '') {
                $prog = $this->primeiro($progresso, static fn (array $p): bool => ($p['courseId'] ?? null) === $principal && ($p['userId'] ?? null) === ($e['userId'] ?? null));
                $temCertificado = $this->primeiro($certificados, static fn (array $c): bool => ($c['courseId'] ?? null) === $principal && ($c['userId'] ?? null) === ($e['userId'] ?? null)) !== null;

                $linhas[] = [
                    'id_matricula' => $idDaMatricula,
                    'id_aluno' => $e['userId'] ?? null,
                    'id_curso' => $principal,
                    'data_matricula' => $this->dia($e['enrolledAt'] ?? null),
                    'status_matricula' => 'Ativa',
                    'modulos_concluidos' => is_array($prog['completedLessons'] ?? null) ? count($prog['completedLessons']) : 0,
                    'certificado_liberado' => $temCertificado ? 'Sim' : 'Não',
                ];
            }

            $concluidos = is_array($e['completedCourseIds'] ?? null) ? array_values($e['completedCourseIds']) : [];
            foreach ($concluidos as $i => $cursoId) {
                $cert = $this->primeiro($certificados, static fn (array $c): bool => ($c['courseId'] ?? null) === $cursoId && ($c['userId'] ?? null) === ($e['userId'] ?? null));

                $linhas[] = [
                    'id_matricula' => $this->texto($idDaMatricula).'-C'.($i + 1),
                    'id_aluno' => $e['userId'] ?? null,
                    'id_curso' => $cursoId,
                    'data_matricula' => '',
                    'status_matricula' => 'Concluída',
                    'modulos_concluidos' => '',
                    'certificado_liberado' => $cert !== null ? 'Sim' : 'Não',
                ];
            }
        }

        return $linhas;
    }

    /**
     * Progresso por AULA (o nome antigo dizia "módulo", que não existe no banco).
     *
     * @param  array<int, array<string,mixed>>  $progresso
     * @param  array<string, list<array{id:string,title:string}>>  $catalogo  aulas de cada curso
     * @return list<array<string,mixed>>
     */
    private function progresso(array $progresso, array $catalogo): array
    {
        $linhas = [];

        foreach ($progresso as $p) {
            $cursoId = $p['courseId'] ?? null;
            if (! is_string($cursoId) || ! array_key_exists($cursoId, $catalogo)) {
                continue;
            }
            $feitas = is_array($p['completedLessons'] ?? null) ? $p['completedLessons'] : [];

            foreach ($catalogo[$cursoId] as $aula) {
                $linhas[] = [
                    'id_progresso' => $this->texto($p['id'] ?? null).'-'.$aula['id'],
                    'id_matricula' => $p['enrollmentId'] ?? '',
                    'id_aluno' => $p['userId'] ?? null,
                    'id_curso' => $cursoId,
                    'id_aula' => $aula['id'],
                    'titulo_aula' => $aula['title'],
                    'status_aula' => in_array($aula['id'], $feitas, true) ? 'Concluído' : 'Não Iniciado',
                ];
            }
        }

        return $linhas;
    }

    /**
     * @param  array<int, array<string,mixed>>  $certificados
     * @return list<array<string,mixed>>
     */
    private function certificados(array $certificados): array
    {
        $cargas = $this->cargasHorarias();

        return array_values(array_map(fn (array $c): array => [
            'id_certificado' => $c['id'] ?? null,
            'id_matricula' => $c['enrollmentId'] ?? '',
            'id_aluno' => $c['userId'] ?? $c['studentName'] ?? null,
            'id_curso' => $c['courseId'] ?? null,
            'codigo_validacao' => $c['verificationHash'] ?? null,
            'data_emissao' => $c['issueDate'] ?? null,
            'percentual_frequencia' => $c['attendancePercent'] ?? null,
            'carga_horaria_certificada' => is_string($c['courseId'] ?? null) ? ($cargas[$c['courseId']] ?? '') : '',
        ], $certificados));
    }

    /**
     * As aulas de cada curso do catálogo, na ordem da grade — o que o painel
     * antigo lia de `/api/courses`.
     *
     * @return array<string, list<array{id:string,title:string}>>
     */
    private function catalogo(): array
    {
        $mapa = [];
        foreach (Course::query()->get(['id']) as $curso) {
            $mapa[(string) $curso->id] = [];
        }

        foreach (Lesson::query()->orderBy('lesson_order')->orderBy('id')->get(['id', 'courseId', 'title']) as $aula) {
            $cursoId = (string) $aula->courseId;
            if (array_key_exists($cursoId, $mapa)) {
                $mapa[$cursoId][] = ['id' => (string) $aula->id, 'title' => (string) $aula->title];
            }
        }

        return $mapa;
    }

    /** @return array<string, int|string> */
    private function cargasHorarias(): array
    {
        $mapa = [];
        foreach (Course::query()->get(['id', 'cargaHoraria']) as $curso) {
            $carga = $curso->cargaHoraria;
            $mapa[(string) $curso->id] = is_numeric($carga) ? (int) $carga : '';
        }

        return $mapa;
    }

    /**
     * @param  array<int, array<string,mixed>>  $lista
     * @param  callable(array<string,mixed>):bool  $teste
     * @return array<string,mixed>|null
     */
    private function primeiro(array $lista, callable $teste): ?array
    {
        foreach ($lista as $item) {
            if ($teste($item)) {
                return $item;
            }
        }

        return null;
    }

    /** `String(e.enrolledAt).split('T')[0]`, ou vazio. */
    private function dia(mixed $instante): string
    {
        if ($instante instanceof DateTimeInterface) {
            return $instante->format('Y-m-d');
        }
        if (! is_string($instante) || $instante === '') {
            return '';
        }

        return explode('T', $instante)[0];
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    /** Um campo do CSV, com a mesma regra de aspas do painel antigo. */
    private function campo(mixed $valor): string
    {
        $texto = match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'true' : 'false',
            is_scalar($valor) => (string) $valor,
            default => '',
        };

        $escapado = str_replace('"', '""', $texto);
        if (str_contains($escapado, ';') || str_contains($escapado, "\n") || str_contains($escapado, '"')) {
            return '"'.$escapado.'"';
        }

        return $escapado;
    }
}
