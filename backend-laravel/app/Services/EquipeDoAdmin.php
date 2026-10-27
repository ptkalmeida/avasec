<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Models\Usuario;

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
        $consulta = Usuario::query()->where('papel', 'instructor')->orderBy('nome');

        $termo = trim((string) $busca);
        if ($termo !== '') {
            // `%` e `_` digitados pela pessoa são texto, não curinga.
            $literal = addcslashes($termo, '\\%_');
            $consulta->where('nome', 'like', '%'.$literal.'%');
        }

        $pessoas = $consulta->get(['id', 'nome', 'status']);
        if ($pessoas->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($pessoas as $pessoa) {
            $ids[] = (string) $pessoa->id;
        }

        $cursosDe = [];
        foreach (Curso::query()->whereIn('instrutor_id', $ids)->orderBy('titulo')->get(['id', 'titulo', 'instrutor_id']) as $curso) {
            $cursosDe[(string) $curso->instrutor_id][] = ['id' => (string) $curso->id, 'titulo' => (string) $curso->titulo];
        }

        $lista = [];
        foreach ($pessoas as $pessoa) {
            $lista[] = [
                'id' => (string) $pessoa->id,
                'nome' => (string) $pessoa->nome,
                'situacao' => (string) $pessoa->status,
                'cursos' => $cursosDe[(string) $pessoa->id] ?? [],
            ];
        }

        return $lista;
    }

    /** Quantos gestores existem, com ou sem busca — o "(N)" do cabeçalho. */
    public function total(): int
    {
        return Usuario::query()->where('papel', 'instructor')->count();
    }
}
