<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\User;

/**
 * A lista da "Equipe Pedagógica" do painel administrativo: os gestores de
 * curso e as trilhas que cada um conduz.
 *
 * Somente leitura, como a tela React que ela substitui. Aquela tela tinha um
 * cartão "Configurações do Gestor" com uma "Especialidade Principal" escrita no
 * código — a mesma para qualquer gestor — e o nome genérico "Gestor de
 * Conteúdos"; nenhum dado vinha do banco, então o cartão não veio.
 */
final class EquipeDoAdmin
{
    /**
     * @return list<array{id:string,nome:string,situacao:string,cursos:list<array{id:string,titulo:string}>}>
     */
    public function listar(?string $busca): array
    {
        $consulta = User::query()->where('role', 'instructor')->orderBy('name');

        $termo = trim((string) $busca);
        if ($termo !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($termo, '\\%_');
            $consulta->where('name', 'like', '%'.$literal.'%');
        }

        $pessoas = $consulta->get(['id', 'name', 'status']);
        if ($pessoas->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($pessoas as $pessoa) {
            $ids[] = (string) $pessoa->id;
        }

        $cursosDe = [];
        foreach (Course::query()->whereIn('instructorId', $ids)->orderBy('title')->get(['id', 'title', 'instructorId']) as $curso) {
            $cursosDe[(string) $curso->instructorId][] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->title];
        }

        $lista = [];
        foreach ($pessoas as $pessoa) {
            $lista[] = [
                'id' => (string) $pessoa->id,
                'nome' => (string) $pessoa->name,
                'situacao' => (string) $pessoa->status,
                'cursos' => $cursosDe[(string) $pessoa->id] ?? [],
            ];
        }

        return $lista;
    }

    /** Quantos gestores existem, com ou sem busca — o "(N)" do cabeçalho. */
    public function total(): int
    {
        return User::query()->where('role', 'instructor')->count();
    }
}
