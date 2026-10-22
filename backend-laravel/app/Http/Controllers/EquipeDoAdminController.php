<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EquipeDoAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Equipe Pedagógica" do painel administrativo, em Blade (M5, fatia 3).
 *
 * Só leitura e busca por nome — é o que a tela React fazia. O painel React não
 * tinha cadastro, inativação nem redefinição de senha de gestor nesta aba (o
 * formulário de criação existia no código, mas nenhuma tela o chamava), e nada
 * disso foi acrescentado aqui: função nova é decisão do responsável.
 */
final class EquipeDoAdminController extends Controller
{
    public function __construct(private readonly EquipeDoAdmin $equipe) {}

    public function listar(Request $request): View
    {
        $busca = trim((string) $request->query('busca', ''));

        return view('admin.equipe', [
            'busca' => $busca,
            'gestores' => $this->equipe->listar($busca),
            'total' => $this->equipe->total(),
        ]);
    }
}
