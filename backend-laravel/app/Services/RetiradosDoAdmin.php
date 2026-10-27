<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Models\Aula;
use App\Models\Usuario;
use App\Support\Fuso;
use App\Support\VigenciaDoCurso;
use DateTimeInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

/**
 * O que saiu do ar e continua guardado: cursos, alunos e gestores retirados.
 *
 * Nada é apagado (ADR 12): quem tira do ar é registrado — quem, quando e, se foi
 * informado, por quê — e a linha permanece íntegra. Só que, até esta classe, **nenhuma
 * tela do administrador mostrava isso**: o que saía do ar virava um limbo, guardado
 * no banco e sem como voltar pela interface. Esta é a leitura que as três telas
 * (Cursos & Trilhas, Alunos, Equipe Pedagógica) usam para exibir a seção "Retirados".
 *
 * Somente leitura; reativar passa pelos services de escrita.
 *
 * @phpstan-type Retirado array{id:string,titulo:string,detalhes:list<string>,retiradoEm:?string,retiradoPor:?string,motivo:?string}
 */
final class RetiradosDoAdmin
{
    /**
     * Teto por seção. Retirar é raro; o limite existe para que uma base grande
     * nunca transforme a tela numa consulta sem fim, e o "(N)" do título mostra o
     * que foi exibido.
     */
    private const LIMITE = 200;

    /**
     * @return list<Retirado>
     */
    public function cursos(): array
    {
        $cursos = Curso::onlyTrashed()->orderByDesc('inativado_em')->limit(self::LIMITE)->get();
        if ($cursos->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($cursos as $curso) {
            $ids[] = (string) $curso->id;
        }

        // As aulas continuam ativas por baixo da disciplina retirada (CourseService::deleteCourse),
        // então a contagem é a do que voltará ao ar.
        $aulas = [];
        foreach (Aula::query()->toBase()->whereIn('curso_id', $ids)->groupBy('curso_id')->pluck(new Expression('count(*)'), 'curso_id') as $cursoId => $total) {
            if (is_numeric($total)) {
                $aulas[(string) $cursoId] = (int) $total;
            }
        }

        $quem = $this->nomes($cursos->pluck('inativado_por'));

        $lista = [];
        foreach ($cursos as $curso) {
            $id = (string) $curso->id;
            $linha = [
                'vigencia_ate' => $curso->getAttribute('vigencia_ate'),
                'vencimento_contrato_texto' => $curso->vencimento_contrato_texto,
            ];

            $detalhes = [];
            if (is_string($curso->categoria) && $curso->categoria !== '') {
                $detalhes[] = $curso->categoria;
            }
            $total = $aulas[$id] ?? 0;
            $detalhes[] = $total.($total === 1 ? ' aula' : ' aulas');
            if (is_string($curso->nome_instrutor) && $curso->nome_instrutor !== '') {
                $detalhes[] = 'Gestor: '.$curso->nome_instrutor;
            }
            $vigencia = VigenciaDoCurso::diaParaPessoa($linha);
            if ($vigencia !== null) {
                $detalhes[] = (VigenciaDoCurso::vencida($linha) ? 'Vigência vencida em ' : 'Vigência até ').$vigencia;
            }

            $lista[] = $this->linha($id, (string) $curso->titulo, $detalhes, $curso, $quem);
        }

        return $lista;
    }

    /**
     * @return list<Retirado>
     */
    public function alunos(): array
    {
        return $this->pessoas('student');
    }

    /**
     * @return list<Retirado>
     */
    public function gestores(): array
    {
        return $this->pessoas('instructor');
    }

    /**
     * @return list<Retirado>
     */
    private function pessoas(string $papel): array
    {
        $pessoas = Usuario::onlyTrashed()->where('papel', $papel)->orderByDesc('inativado_em')->limit(self::LIMITE)->get();
        if ($pessoas->isEmpty()) {
            return [];
        }

        $quem = $this->nomes($pessoas->pluck('inativado_por'));

        $lista = [];
        foreach ($pessoas as $pessoa) {
            $detalhes = [];
            if (is_string($pessoa->email) && $pessoa->email !== '') {
                $detalhes[] = $pessoa->email;
            }

            $lista[] = $this->linha((string) $pessoa->id, (string) $pessoa->nome, $detalhes, $pessoa, $quem);
        }

        return $lista;
    }

    /**
     * @param  list<string>  $detalhes
     * @param  array<string,string>  $quem
     * @return Retirado
     */
    private function linha(string $id, string $titulo, array $detalhes, Curso|Usuario $registro, array $quem): array
    {
        $porId = $registro->inativado_por;
        $motivo = is_string($registro->motivo_inativacao) ? trim($registro->motivo_inativacao) : '';

        return [
            'id' => $id,
            'titulo' => $titulo,
            'detalhes' => $detalhes,
            'retiradoEm' => $this->quando($registro->inativado_em),
            // Quem retirou nem sempre é encontrado (conta antiga): mostra "—" em vez de omitir a linha.
            'retiradoPor' => is_string($porId) && $porId !== '' ? ($quem[$porId] ?? null) : null,
            'motivo' => $motivo !== '' ? $motivo : null,
        ];
    }

    /**
     * @param  Collection<int,mixed>  $ids
     * @return array<string,string>
     */
    private function nomes(Collection $ids): array
    {
        $unicos = $ids->filter(static fn ($id): bool => is_string($id) && $id !== '')->unique()->values()->all();
        if ($unicos === []) {
            return [];
        }

        $mapa = [];
        // withTrashed: quem retirou pode ter sido retirado depois, e o nome continua valendo.
        foreach (Usuario::withTrashed()->whereIn('id', $unicos)->get(['id', 'nome']) as $pessoa) {
            $mapa[(string) $pessoa->id] = (string) $pessoa->nome;
        }

        return $mapa;
    }

    private function quando(mixed $instante): ?string
    {
        if (! $instante instanceof DateTimeInterface) {
            return null;
        }

        return Fuso::local($instante)?->format('d/m/Y \à\s H:i');
    }
}
