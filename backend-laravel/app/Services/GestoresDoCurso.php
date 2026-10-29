<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Models\CursoGestor;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Quem gerencia cada curso. Fonte única desta regra de posse, que antes era
 * "`curso.instrutor_id` é igual a mim" repetida em quatro lugares.
 *
 * Um curso tem um gestor RESPONSÁVEL (`curso.instrutor_id`, que a vitrine e o
 * certificado mostram) e pode ter outros, em `curso_gestor`. Gerencia quem é um
 * ou outro. Curso sem nenhum dos dois é permitido: só a coordenação o edita.
 *
 * Desfazer vínculo inativa a linha de `curso_gestor` (ADR 12), e vincular de novo
 * a reativa. Retirar o gestor da equipe NÃO mexe nos vínculos: reativá-lo devolve
 * tudo como estava (a conta retirada simplesmente não entra para gerenciar).
 */
final class GestoresDoCurso
{
    public function gerencia(string $cursoId, string $usuarioId): bool
    {
        if (Curso::query()->whereKey($cursoId)->where('instrutor_id', $usuarioId)->exists()) {
            return true;
        }

        return CursoGestor::query()->where('curso_id', $cursoId)->where('usuario_id', $usuarioId)->exists();
    }

    /**
     * IDs dos cursos que a pessoa gerencia, como responsável ou como gestora adicional.
     *
     * @return list<string>
     */
    public function cursoIdsDe(string $usuarioId): array
    {
        $ids = array_merge(
            Curso::query()->where('instrutor_id', $usuarioId)->pluck('id')->all(),
            CursoGestor::query()
                ->where('usuario_id', $usuarioId)
                ->whereIn('curso_id', Curso::query()->select('id'))
                ->pluck('curso_id')->all(),
        );

        return array_values(array_unique(array_filter($ids, static fn ($id): bool => is_string($id) && $id !== '')));
    }

    /**
     * Como `gerencia`, mas também para curso RETIRADO (inativado). Existe para o gestor poder
     * reativar o curso que ele mesmo retirou: `gerencia` não enxerga o retirado, de propósito,
     * e quem usa o escopo comum (listas, alunos, mensagens) continua sem ele (ADR 029).
     * O vínculo em `curso_gestor` continua valendo só enquanto ativo.
     */
    public function gerenciaIncluindoRetirados(string $cursoId, string $usuarioId): bool
    {
        if (Curso::withTrashed()->whereKey($cursoId)->where('instrutor_id', $usuarioId)->exists()) {
            return true;
        }

        return CursoGestor::query()
            ->where('curso_id', $cursoId)
            ->where('usuario_id', $usuarioId)
            ->whereIn('curso_id', Curso::withTrashed()->select('id'))
            ->exists();
    }

    /**
     * Como `cursoIdsDe`, incluindo os cursos retirados. Só a tela "Meus cursos" usa, para mostrar
     * o retirado como inativo com o botão de reativar; o escopo de dados segue por `cursoIdsDe`.
     *
     * @return list<string>
     */
    public function cursoIdsDeIncluindoRetirados(string $usuarioId): array
    {
        $ids = array_merge(
            Curso::withTrashed()->where('instrutor_id', $usuarioId)->pluck('id')->all(),
            CursoGestor::query()
                ->where('usuario_id', $usuarioId)
                ->whereIn('curso_id', Curso::withTrashed()->select('id'))
                ->pluck('curso_id')->all(),
        );

        return array_values(array_unique(array_filter($ids, static fn ($id): bool => is_string($id) && $id !== '')));
    }

    /**
     * Os cursos de cada gestor, para a tela da equipe: `[usuario_id => [[id, titulo], ...]]`.
     *
     * @param  list<string>  $usuarioIds
     * @return array<string, list<array{id:string,titulo:string}>>
     */
    public function cursosPorGestor(array $usuarioIds): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        $porId = [];
        $resultado = [];

        foreach (Curso::query()->whereIn('instrutor_id', $usuarioIds)->get(['id', 'titulo', 'instrutor_id']) as $curso) {
            $porId[(string) $curso->id] = (string) $curso->titulo;
            $resultado[(string) $curso->instrutor_id][(string) $curso->id] = true;
        }

        $vinculos = CursoGestor::query()->whereIn('usuario_id', $usuarioIds)->get(['curso_id', 'usuario_id']);
        if (! $vinculos->isEmpty()) {
            $faltam = [];
            foreach ($vinculos as $v) {
                if (! isset($porId[(string) $v->curso_id])) {
                    $faltam[] = (string) $v->curso_id;
                }
            }
            foreach (Curso::query()->whereIn('id', array_unique($faltam))->get(['id', 'titulo']) as $curso) {
                $porId[(string) $curso->id] = (string) $curso->titulo;
            }
            foreach ($vinculos as $v) {
                // Curso inativado não está em `$porId`: sai da lista junto com ele.
                if (isset($porId[(string) $v->curso_id])) {
                    $resultado[(string) $v->usuario_id][(string) $v->curso_id] = true;
                }
            }
        }

        $saida = [];
        foreach ($resultado as $usuarioId => $cursos) {
            $lista = [];
            foreach (array_keys($cursos) as $cursoId) {
                $lista[] = ['id' => (string) $cursoId, 'titulo' => $porId[(string) $cursoId]];
            }
            usort($lista, static fn (array $a, array $b): int => strcasecmp($a['titulo'], $b['titulo']));
            $saida[(string) $usuarioId] = $lista;
        }

        return $saida;
    }

    /**
     * Cursos sem nenhum gestor ATIVO: sem responsável, ou só com gestores retirados da
     * equipe. Só a coordenação os edita até alguém ser escolhido.
     *
     * @return list<array{id:string,titulo:string}>
     */
    public function cursosSemGestorAtivo(): array
    {
        $gestoresAtivos = Usuario::query()->where('papel', 'instructor')->select('id');

        $comGestor = array_merge(
            Curso::query()->whereIn('instrutor_id', $gestoresAtivos)->pluck('id')->all(),
            CursoGestor::query()->whereIn('usuario_id', $gestoresAtivos)->pluck('curso_id')->all(),
        );

        $lista = [];
        foreach (Curso::query()->whereNotIn('id', $comGestor)->orderBy('titulo')->get(['id', 'titulo']) as $curso) {
            $lista[] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->titulo];
        }

        return $lista;
    }

    /**
     * Deixa a pessoa gerenciando EXATAMENTE os cursos informados: vincula os que
     * faltam e desfaz os que sobram. Ids que não são de curso existente são ignorados.
     *
     * @param  list<string>  $cursoIds
     */
    public function definirCursosDoGestor(string $usuarioId, array $cursoIds, ?string $porUsuarioId = null): void
    {
        $desejados = array_values(array_unique(array_filter($cursoIds, static fn ($id): bool => is_string($id) && $id !== '')));
        $validos = [];
        foreach (Curso::query()->whereIn('id', $desejados)->get(['id']) as $curso) {
            $validos[] = (string) $curso->id;
        }
        $atuais = $this->cursoIdsDe($usuarioId);

        DB::transaction(function () use ($usuarioId, $validos, $atuais, $porUsuarioId): void {
            foreach (array_diff($validos, $atuais) as $cursoId) {
                $this->vincular($cursoId, $usuarioId);
            }
            foreach (array_diff($atuais, $validos) as $cursoId) {
                $this->desvincular($cursoId, $usuarioId, $porUsuarioId);
            }
        });
    }

    private function nomeDe(string $usuarioId): string
    {
        return (string) Usuario::withTrashed()->find($usuarioId)?->nome;
    }

    /** O curso ganha a pessoa como gestora: responsável se estava sem, adicional se já tinha. */
    private function vincular(string $cursoId, string $usuarioId): void
    {
        $curso = Curso::query()->lockForUpdate()->find($cursoId);
        if ($curso === null) {
            return;
        }

        if ($curso->instrutor_id === null || $curso->instrutor_id === '') {
            $curso->instrutor_id = $usuarioId;
            $curso->nome_instrutor = $this->nomeDe($usuarioId);
            $curso->saveQuietly();

            return;
        }

        $vinculo = CursoGestor::withTrashed()->where('curso_id', $cursoId)->where('usuario_id', $usuarioId)->first();
        if ($vinculo === null) {
            CursoGestor::query()->create(['curso_id' => $cursoId, 'usuario_id' => $usuarioId]);
        } elseif ($vinculo->estaInativo()) {
            $vinculo->reativar();
        }
    }

    /**
     * A pessoa deixa de gerenciar o curso. Se era a responsável, passa a ser
     * responsável a gestora adicional mais antiga (o vínculo dela é inativado, para
     * não ficar em dois lugares); sem ninguém, o curso fica sem gestor.
     */
    private function desvincular(string $cursoId, string $usuarioId, ?string $porUsuarioId): void
    {
        $curso = Curso::query()->lockForUpdate()->find($cursoId);
        if ($curso === null) {
            return;
        }

        if ($curso->instrutor_id === $usuarioId) {
            $proximo = CursoGestor::query()->where('curso_id', $cursoId)->where('usuario_id', '!=', $usuarioId)->orderBy('id')->first();
            if ($proximo === null) {
                $curso->instrutor_id = null;
                $curso->nome_instrutor = '';
            } else {
                $curso->instrutor_id = $proximo->usuario_id;
                $curso->nome_instrutor = $this->nomeDe($proximo->usuario_id);
                $proximo->inativar($porUsuarioId, 'promovido a responsável');
            }
            $curso->saveQuietly();
        }

        $vinculo = CursoGestor::query()->where('curso_id', $cursoId)->where('usuario_id', $usuarioId)->first();
        $vinculo?->inativar($porUsuarioId, 'vínculo desfeito pela coordenação');
    }
}
