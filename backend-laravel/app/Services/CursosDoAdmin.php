<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Models\Aula;
use App\Models\Avaliacao;
use App\Models\Matricula;
use App\Models\Usuario;
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
        $consulta = Curso::query()->orderBy('titulo');

        $termo = trim((string) $busca);
        if ($termo !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($termo, '\\%_');
            $consulta->where(static function ($q) use ($literal): void {
                $q->where('titulo', 'like', '%'.$literal.'%')
                    ->orWhere('nome_instrutor', 'like', '%'.$literal.'%');
            });
        }

        $cursos = $consulta->get();
        if ($cursos->isEmpty()) {
            return [];
        }

        $aulas = [];
        foreach (Aula::query()->toBase()->groupBy('curso_id')->pluck(new Expression('count(*)'), 'curso_id') as $cursoId => $total) {
            if (is_numeric($total)) {
                $aulas[(string) $cursoId] = (int) $total;
            }
        }

        $avaliacoes = [];
        foreach (Avaliacao::query()->toBase()->groupBy('curso_id')->pluck(new Expression('count(*)'), 'curso_id') as $cursoId => $total) {
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
                'vigencia_ate' => $curso->getAttribute('vigencia_ate'),
                'vencimento_contrato_texto' => $curso->vencimento_contrato_texto,
            ];

            $lista[] = [
                'id' => $id,
                'ref' => is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : $id,
                'titulo' => (string) $curso->titulo,
                'categoria' => is_string($curso->categoria) && $curso->categoria !== '' ? $curso->categoria : null,
                'descricao' => (string) $curso->descricao,
                'aulas' => $aulas[$id] ?? 0,
                'avaliacoes' => $avaliacoes[$id] ?? 0,
                'instrutorId' => is_string($curso->instrutor_id) && $curso->instrutor_id !== '' ? $curso->instrutor_id : null,
                'instrutorNome' => is_string($curso->nome_instrutor) && $curso->nome_instrutor !== '' ? $curso->nome_instrutor : null,
                'tipo' => $curso->tipo_curso === 'ao_vivo' ? 'ao_vivo' : 'fixo',
                // Sem valor gravado, o painel sempre tratou o chat como ligado.
                'chat' => $curso->tem_chat !== false,
                'capa' => is_string($curso->imagem_capa) && $curso->imagem_capa !== '' ? $curso->imagem_capa : null,
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
        foreach (Curso::query()->whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->orderBy('categoria')->pluck('categoria') as $nome) {
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
        foreach (Usuario::query()->where('papel', 'instructor')->orderBy('nome')->get(['id', 'nome']) as $pessoa) {
            $lista[] = ['id' => (string) $pessoa->id, 'nome' => (string) $pessoa->nome];
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
        foreach (Matricula::query()->get(['usuario_id', 'curso_matriculado_id', 'cursos_extras_ids']) as $matricula) {
            $ids = [];
            if (is_string($matricula->curso_matriculado_id) && $matricula->curso_matriculado_id !== '') {
                $ids[] = $matricula->curso_matriculado_id;
            }
            $extras = $matricula->cursos_extras_ids;
            foreach (is_array($extras) ? $extras : [] as $extra) {
                if (is_string($extra) && $extra !== '') {
                    $ids[] = $extra;
                }
            }
            foreach (array_unique($ids) as $cursoId) {
                $cursosDe[$cursoId][] = (string) $matricula->usuario_id;
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
        foreach (Usuario::query()->whereIn('id', array_keys($todos))->get(['id', 'nome', 'email']) as $pessoa) {
            $pessoas[(string) $pessoa->id] = [
                'nome' => (string) $pessoa->nome,
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
