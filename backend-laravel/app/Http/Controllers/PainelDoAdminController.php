<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RelatoriosDoAdmin;
use App\Support\MenuDoAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard e Relatórios do painel administrativo, em Blade (M5, fatia 2).
 *
 * Só leitura. Cada sub-aba é um endereço — `/admin` (Visão Geral) e
 * `/admin/relatorio/{alunos|professores|cursos|inscricoes}` —, os mesmos que o
 * painel React já usava, para que um link guardado continue levando ao mesmo
 * lugar. Os números vêm do `RelatoriosDoAdmin`, e nenhuma conta é feita aqui.
 */
final class PainelDoAdminController extends Controller
{
    /** Sub-aba → rótulo. A ordem é a das abas na tela. */
    private const ABAS = [
        'consolidado' => 'Visão Geral',
        'alunos' => 'Alunos',
        'professores' => 'Equipe Pedagógica',
        'cursos' => 'Cursos',
        'inscricoes' => 'Matrículas',
    ];

    public function __construct(private readonly RelatoriosDoAdmin $relatorios) {}

    /**
     * Uma seção do painel que existe no sistema mas está DESLIGADA por flag.
     *
     * Exercícios práticos e solicitações acadêmicas têm flag própria em
     * `config/features.php`, as duas desligadas. Nenhuma foi reconstruída em
     * Blade: tela para função que ninguém pode usar seria código sem leitor. O
     * ENDEREÇO, porém, é do servidor — senão `/admin/exercicios` cairia no React,
     * e o painel antigo seguiria alcançável por ali.
     *
     * Ligada a flag, a rota responde 501 e `SecoesDesligadasDoAdminTest` falha
     * dizendo qual tela construir.
     */
    public function secaoDesligada(string $flag, string $secao, string $explicacao): View
    {
        abort_if(config('features.'.$flag) === true, 501);

        $item = MenuDoAdmin::item($secao);
        abort_if($item === null, 404);

        return view('admin.secao-desligada', ['item' => $item, 'explicacao' => $explicacao]);
    }

    public function dashboard(Request $request, ?string $sub = null): View|RedirectResponse
    {
        $sub ??= 'consolidado';

        // `/admin/relatorio/consolidado` seria a mesma tela com dois endereços;
        // sub-aba inventada também volta para a entrada.
        if ($request->route('sub') !== null && ($sub === 'consolidado' || ! isset(self::ABAS[$sub]))) {
            return redirect('/admin');
        }

        $pagina = max(1, (int) $request->query('pagina', '1'));

        $dados = match ($sub) {
            'alunos' => $this->relatorios->alunos($pagina),
            'professores' => ['linhas' => $this->relatorios->equipe()],
            'cursos' => ['linhas' => $this->relatorios->cursos()],
            'inscricoes' => $this->relatorios->matriculas($pagina),
            default => ['resumo' => $this->relatorios->visaoGeral()],
        };

        $total = is_int($dados['total'] ?? null) ? $dados['total'] : null;
        $ultima = $total === null ? 1 : max(1, (int) ceil($total / RelatoriosDoAdmin::POR_PAGINA));

        // Página além do fim volta para a última: a lista cresce e encolhe, e um
        // endereço guardado não pode cair numa tabela vazia.
        if ($pagina > $ultima) {
            return redirect('/admin/relatorio/'.$sub.'?pagina='.$ultima);
        }

        return view('admin.dashboard', [
            'abas' => self::ABAS,
            'sub' => $sub,
            'dados' => $dados,
            'pagina' => $pagina,
            'ultimaPagina' => $ultima,
        ]);
    }
}
