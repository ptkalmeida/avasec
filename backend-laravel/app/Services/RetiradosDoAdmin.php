<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
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
        $cursos = Course::onlyTrashed()->orderByDesc('inativadoEm')->limit(self::LIMITE)->get();
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
        foreach (Lesson::query()->toBase()->whereIn('courseId', $ids)->groupBy('courseId')->pluck(new Expression('count(*)'), 'courseId') as $cursoId => $total) {
            if (is_numeric($total)) {
                $aulas[(string) $cursoId] = (int) $total;
            }
        }

        $quem = $this->nomes($cursos->pluck('inativadoPor'));

        $lista = [];
        foreach ($cursos as $curso) {
            $id = (string) $curso->id;
            $linha = [
                'vigenciaAte' => $curso->getAttribute('vigenciaAte'),
                'contractExpirationDate' => $curso->contractExpirationDate,
            ];

            $detalhes = [];
            if (is_string($curso->category) && $curso->category !== '') {
                $detalhes[] = $curso->category;
            }
            $total = $aulas[$id] ?? 0;
            $detalhes[] = $total.($total === 1 ? ' aula' : ' aulas');
            if (is_string($curso->instructorName) && $curso->instructorName !== '') {
                $detalhes[] = 'Gestor: '.$curso->instructorName;
            }
            $vigencia = VigenciaDoCurso::diaParaPessoa($linha);
            if ($vigencia !== null) {
                $detalhes[] = (VigenciaDoCurso::vencida($linha) ? 'Vigência vencida em ' : 'Vigência até ').$vigencia;
            }

            $lista[] = $this->linha($id, (string) $curso->title, $detalhes, $curso, $quem);
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
        $pessoas = User::onlyTrashed()->where('role', $papel)->orderByDesc('inativadoEm')->limit(self::LIMITE)->get();
        if ($pessoas->isEmpty()) {
            return [];
        }

        $quem = $this->nomes($pessoas->pluck('inativadoPor'));

        $lista = [];
        foreach ($pessoas as $pessoa) {
            $detalhes = [];
            if (is_string($pessoa->email) && $pessoa->email !== '') {
                $detalhes[] = $pessoa->email;
            }

            $lista[] = $this->linha((string) $pessoa->id, (string) $pessoa->name, $detalhes, $pessoa, $quem);
        }

        return $lista;
    }

    /**
     * @param  list<string>  $detalhes
     * @param  array<string,string>  $quem
     * @return Retirado
     */
    private function linha(string $id, string $titulo, array $detalhes, Course|User $registro, array $quem): array
    {
        $porId = $registro->inativadoPor;
        $motivo = is_string($registro->motivoInativacao) ? trim($registro->motivoInativacao) : '';

        return [
            'id' => $id,
            'titulo' => $titulo,
            'detalhes' => $detalhes,
            'retiradoEm' => $this->quando($registro->inativadoEm),
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
        foreach (User::withTrashed()->whereIn('id', $unicos)->get(['id', 'name']) as $pessoa) {
            $mapa[(string) $pessoa->id] = (string) $pessoa->name;
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
