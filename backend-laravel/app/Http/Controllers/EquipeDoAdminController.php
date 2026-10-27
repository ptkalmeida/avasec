<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\EquipeDoAdmin;
use App\Services\RetiradosDoAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Equipe Pedagógica" do painel administrativo, em Blade (M5, fatia 3).
 *
 * A lista, a busca por nome e a seção dos gestores retirados (com o botão de
 * reativar) — esta última acrescentada em 26/10/2026 por decisão do responsável.
 * O painel React não tinha cadastro, inativação nem redefinição de senha de
 * gestor nesta aba (o formulário de criação existia no código, mas nenhuma tela
 * o chamava): essas funções são pedido novo, em plano próprio.
 */
final class EquipeDoAdminController extends Controller
{
    public function __construct(
        private readonly EquipeDoAdmin $equipe,
        private readonly RetiradosDoAdmin $retirados,
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    public function listar(Request $request): View
    {
        $busca = trim((string) $request->query('busca', ''));

        return view('admin.equipe', [
            'busca' => $busca,
            'gestores' => $this->equipe->listar($busca),
            'retirados' => $this->retirados->gestores(),
            'total' => $this->equipe->total(),
        ]);
    }

    /**
     * Devolver à equipe um gestor retirado. A conta volta ativa (a retirada a bloqueou) e as
     * sessões anteriores são encerradas, então a pessoa entra com login novo. As disciplinas
     * que ela gerencia nunca saíram do vínculo (a retirada inativa a conta, não o `instructorId`),
     * e voltam a aparecer sob a gestão dela.
     */
    public function reativar(Request $request, string $gestorId): RedirectResponse
    {
        $gestor = User::onlyTrashed()->where('role', 'instructor')->find($gestorId);
        abort_if($gestor === null, 404);

        $this->auth->reactivateUser($gestorId);
        $this->audit->log($request, 'Reativação de Usuário', 'Gestor '.$gestorId.' reativado pela coordenação (conta ativa; sessões anteriores encerradas).', 'WARNING');

        return redirect('/admin/equipe')->with(
            'avisoDaEquipe',
            $gestor->name.' voltou à equipe. A conta está ativa; a pessoa entra com um login novo.',
        );
    }
}
