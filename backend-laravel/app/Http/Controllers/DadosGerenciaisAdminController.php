<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\BasesGerenciais;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Dados Gerenciais" do painel administrativo, em Blade (M5, fatia 7): as cinco
 * bases em CSV para Power BI, Excel e relatórios de gestão.
 *
 * O que o painel React MOSTRAVA na pré-visualização era montado no navegador,
 * com valores de reserva inventados quando o dado faltava ("São Paulo", "SP",
 * "ALU_001", "2026-06-01", "Design Digital") — enquanto o ARQUIVO baixado vinha
 * do servidor, sem nada disso. A prévia mostrava, então, uma coisa diferente da
 * que o arquivo trazia. Aqui as duas saem da mesma fonte: o que se vê é o que
 * se baixa.
 *
 * Cada download é auditado e passa pelo mesmo limitador da API de exportação.
 */
final class DadosGerenciaisAdminController extends Controller
{
    /** Quantas linhas a prévia mostra. */
    private const PREVIA = 5;

    public function __construct(
        private readonly BasesGerenciais $bases,
        private readonly AuditLogger $audit,
    ) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        abort_unless(config('features.dadosGerenciais') === true, 404);

        $pedida = $request->query('base');
        $base = is_string($pedida) && $pedida !== '' ? $pedida : 'alunos';
        if (! array_key_exists($base, BasesGerenciais::BASES)) {
            return redirect('/admin/dados-gerenciais');
        }

        $linhas = $this->bases->linhas($base);

        return view('admin.dados-gerenciais', [
            'bases' => BasesGerenciais::BASES,
            'base' => $base,
            'definicao' => BasesGerenciais::BASES[$base],
            'total' => count($linhas),
            'colunas' => $linhas === [] ? [] : array_keys($linhas[0]),
            'previa' => array_slice($linhas, 0, self::PREVIA),
        ]);
    }

    public function baixar(Request $request, string $base): Response|RedirectResponse
    {
        abort_unless(config('features.dadosGerenciais') === true, 404);
        abort_unless(array_key_exists($base, BasesGerenciais::BASES), 404);

        $linhas = $this->bases->linhas($base);
        $csv = $this->bases->csv($linhas);

        // Sem registro nenhum não há planilha: o painel antigo não baixava nada,
        // calado. Aqui a pessoa é avisada — exceto no "baixar todas", que pede as
        // cinco em sequência e não pode ter a página trocada no meio por um aviso.
        if ($csv === null && $request->boolean('lote')) {
            return response()->noContent();
        }
        if ($csv === null) {
            return redirect('/admin/dados-gerenciais?base='.$base)
                ->with('avisoDeDados', 'A '.mb_strtolower(BasesGerenciais::BASES[$base]['titulo']).' não tem registros: não há arquivo para baixar.');
        }

        $this->audit->log($request, 'Exportação de Dados Gerenciais', 'Base "'.$base.'" exportada ('.count($linhas).' registros).');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.BasesGerenciais::BASES[$base]['arquivo'].'"',
            // Dados pessoais: nada de guardar cópia em cache compartilhado.
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
