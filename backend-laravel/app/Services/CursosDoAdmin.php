<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\VigenciaDoCurso;
use Illuminate\Database\Query\Expression;

/**
 * O que a tela "Cursos & Trilhas" do administrador mostra.
 *
 * Somente leitura; as escritas passam pelo `CourseService`, o mesmo da API.
 *
 * ## A lista de "Alunos Ativos" agora é de verdade
 *
 * No painel React, cada curso exibia de 2 a 4 alunos escolhidos por uma conta
 * sobre as letras do id do curso — nomes de pessoas reais da lista, sem nenhuma
 * ligação com matrícula. Aqui a lista é a de quem está matriculado de fato
 * (curso principal ou concorrente), a mesma leitura do painel do instrutor.
 */
final class CursosDoAdmin
{
    /**
     * @return list<array{id:string,ref:string,titulo:string,categoria:?string,descricao:string,aulas:int,avaliacoes:int,instrutorId:?string,instrutorNome:?string,tipo:string,chat:bool,capa:?string,vigenciaDia:?string,vigenciaTexto:?string,vencida:bool,visibilidade:int,alunos:list<array{nome:string,email:?string}>}>
     */
    public function listar(?string $busca): array
    {
        $consulta = Course::query()->orderBy('title');

        $termo = trim((string) $busca);
        if ($termo !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($termo, '\\%_');
            $consulta->where(static function ($q) use ($literal): void {
                $q->where('title', 'like', '%'.$literal.'%')
                    ->orWhere('instructorName', 'like', '%'.$literal.'%');
            });
        }

        $cursos = $consulta->get();
        if ($cursos->isEmpty()) {
            return [];
        }

        $aulas = [];
        foreach (Lesson::query()->toBase()->groupBy('courseId')->pluck(new Expression('count(*)'), 'courseId') as $cursoId => $total) {
            if (is_numeric($total)) {
                $aulas[(string) $cursoId] = (int) $total;
            }
        }

        $avaliacoes = [];
        foreach (Quiz::query()->toBase()->groupBy('courseId')->pluck(new Expression('count(*)'), 'courseId') as $cursoId => $total) {
            if (is_numeric($total)) {
                $avaliacoes[(string) $cursoId] = (int) $total;
            }
        }

        $alunosDe = $this->alunosPorCurso();

        $lista = [];
        foreach ($cursos as $curso) {
            $id = (string) $curso->id;
            $status = $curso->getAttribute('status');
            $linha = [
                'vigenciaAte' => $curso->getAttribute('vigenciaAte'),
                'contractExpirationDate' => $curso->contractExpirationDate,
            ];

            $lista[] = [
                'id' => $id,
                'ref' => is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : $id,
                'titulo' => (string) $curso->title,
                'categoria' => is_string($curso->category) && $curso->category !== '' ? $curso->category : null,
                'descricao' => (string) $curso->description,
                'aulas' => $aulas[$id] ?? 0,
                'avaliacoes' => $avaliacoes[$id] ?? 0,
                'instrutorId' => is_string($curso->instructorId) && $curso->instructorId !== '' ? $curso->instructorId : null,
                'instrutorNome' => is_string($curso->instructorName) && $curso->instructorName !== '' ? $curso->instructorName : null,
                'tipo' => $curso->courseType === 'ao_vivo' ? 'ao_vivo' : 'fixo',
                // Sem valor gravado, o painel sempre tratou o chat como ligado.
                'chat' => $curso->hasChat !== false,
                'capa' => is_string($curso->coverImage) && $curso->coverImage !== '' ? $curso->coverImage : null,
                'vigenciaDia' => VigenciaDoCurso::dia($linha),
                'vigenciaTexto' => VigenciaDoCurso::diaParaPessoa($linha),
                'vencida' => VigenciaDoCurso::vencida($linha),
                'visibilidade' => is_numeric($status) ? (int) $status : 1,
                'alunos' => $alunosDe[$id] ?? [],
            ];
        }

        return $lista;
    }

    /**
     * As áreas já usadas nos cursos — o que a tela oferece ao criar um curso.
     *
     * O painel React guardava uma lista de "áreas de atuação" no armazenamento
     * do NAVEGADOR de quem a editava (`ava_categories`): uma área "habilitada"
     * ali nunca chegava ao servidor nem a outro computador. A área é um texto do
     * curso; o que existe de fato é o que os cursos usam.
     *
     * @return list<string>
     */
    public function categorias(): array
    {
        $nomes = [];
        foreach (Course::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category') as $nome) {
            if (is_string($nome)) {
                $nomes[] = $nome;
            }
        }

        return $nomes;
    }

    /**
     * Quem pode ser responsável por um curso.
     *
     * @return list<array{id:string,nome:string}>
     */
    public function instrutores(): array
    {
        $lista = [];
        foreach (User::query()->where('role', 'instructor')->orderBy('name')->get(['id', 'name']) as $pessoa) {
            $lista[] = ['id' => (string) $pessoa->id, 'nome' => (string) $pessoa->name];
        }

        return $lista;
    }

    /**
     * Quem está matriculado em cada curso agora: principal e concorrentes.
     * Quem já concluiu não está "ativo".
     *
     * @return array<string,list<array{nome:string,email:?string}>>
     */
    private function alunosPorCurso(): array
    {
        $cursosDe = [];
        foreach (StudentEnrollment::query()->get(['userId', 'enrolledCourseId', 'extraCourseIds']) as $matricula) {
            $ids = [];
            if (is_string($matricula->enrolledCourseId) && $matricula->enrolledCourseId !== '') {
                $ids[] = $matricula->enrolledCourseId;
            }
            $extras = $matricula->extraCourseIds;
            foreach (is_array($extras) ? $extras : [] as $extra) {
                if (is_string($extra) && $extra !== '') {
                    $ids[] = $extra;
                }
            }
            foreach (array_unique($ids) as $cursoId) {
                $cursosDe[$cursoId][] = (string) $matricula->userId;
            }
        }

        if ($cursosDe === []) {
            return [];
        }

        $todos = [];
        foreach ($cursosDe as $alunoIds) {
            foreach ($alunoIds as $alunoId) {
                $todos[$alunoId] = true;
            }
        }

        $pessoas = [];
        foreach (User::query()->whereIn('id', array_keys($todos))->get(['id', 'name', 'email']) as $pessoa) {
            $pessoas[(string) $pessoa->id] = [
                'nome' => (string) $pessoa->name,
                'email' => is_string($pessoa->email) && $pessoa->email !== '' ? $pessoa->email : null,
            ];
        }

        $mapa = [];
        foreach ($cursosDe as $cursoId => $alunoIds) {
            foreach ($alunoIds as $alunoId) {
                // Aluno inativado some das consultas padrão e, com ele, da lista.
                if (isset($pessoas[$alunoId])) {
                    $mapa[$cursoId][] = $pessoas[$alunoId];
                }
            }
            if (isset($mapa[$cursoId])) {
                usort($mapa[$cursoId], static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
            }
        }

        return $mapa;
    }
}
