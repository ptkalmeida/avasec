<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\SitePageContentService;
use App\Services\VersoesDeConteudo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Páginas do Site" do painel administrativo, em Blade (M5, fatia 6b).
 *
 * Os formulários nascem do schema que o `SitePageContentService` já serve —
 * rótulos, campos e tetos —, então um campo novo no serviço aparece aqui sem
 * mexer na tela. Tudo é texto puro: o serviço descarta o que o schema não
 * conhece e nada vira HTML na página pública.
 *
 * Acrescentar, remover e mover item mexem só no RASCUNHO da tela; nada vai ao
 * ar até "Salvar e publicar", como no painel React.
 *
 * Salvar substitui o texto publicado, mas o que estava no ar vai para o histórico
 * de versões (ADR 021) e dá para voltar a ele pela própria tela.
 */
final class PaginasDoSiteAdminController extends Controller
{
    public function __construct(
        private readonly SitePageContentService $paginas,
        private readonly AuditLogger $audit,
        private readonly VersoesDeConteudo $versoes,
    ) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        abort_unless(config('features.gestaoConteudoSite') === true, 404);

        $esquema = $this->paginas->schema();
        $chaves = array_keys($esquema);

        $pedida = $request->query('pagina');
        if ($pedida === null || $pedida === '') {
            $pedida = $chaves[0];
        }
        if (! is_string($pedida) || ! array_key_exists($pedida, $esquema)) {
            return redirect('/admin/paginas-do-site');
        }

        $conteudo = $this->paginas->get($pedida);

        // O rascunho (em andamento, ou devolvido por erro) vale mais que o gravado.
        $topo = [];
        $rascunhoDoTopo = $request->old('topo');
        foreach ($esquema[$pedida]['header'] as $campo) {
            $valor = is_array($rascunhoDoTopo) ? ($rascunhoDoTopo[$campo['key']] ?? null) : null;
            $gravado = $conteudo[$campo['key']] ?? '';
            $topo[$campo['key']] = is_string($valor) ? $valor : (is_string($gravado) ? $gravado : '');
        }

        $itens = $this->itens($request->old('itens'), $conteudo['items'] ?? [], $esquema[$pedida]['item']);

        $resumo = [];
        foreach ($chaves as $chave) {
            $outra = $this->paginas->get($chave);
            $resumo[$chave] = is_array($outra['items'] ?? null) ? count($outra['items']) : 0;
        }

        return view('admin.paginas-do-site', [
            'esquema' => $esquema,
            'pagina' => $pedida,
            'definicao' => $esquema[$pedida],
            'topo' => $topo,
            'itens' => $itens,
            'quantosItens' => $resumo,
            'atualizadoEm' => is_string($conteudo['updatedAt'] ?? null) ? $conteudo['updatedAt'] : null,
            'versoes' => $this->versoes->paraTela(VersoesDeConteudo::PAGINA_DO_SITE, $pedida, static function (array $v) use ($esquema, $pedida): string {
                $chaves = array_column($esquema[$pedida]['header'], 'key');
                $chave = in_array('title', $chaves, true) ? 'title' : ($chaves[0] ?? '');
                $titulo = is_string($v[$chave] ?? null) ? trim($v[$chave]) : '';
                $itens = is_array($v['items'] ?? null) ? count($v['items']) : 0;

                return ($titulo !== '' ? mb_strimwidth($titulo, 0, 90, '…') : 'Sem título').' · '.$itens.($itens === 1 ? ' item' : ' itens');
            }),
        ]);
    }

    /** Volta a uma versão anterior da página. O que estava no ar entra no histórico. */
    public function restaurar(Request $request, string $pagina, string $versao): RedirectResponse
    {
        abort_unless(config('features.gestaoConteudoSite') === true, 404);
        abort_unless(array_key_exists($pagina, $this->paginas->schema()), 404);

        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $this->paginas->restaurarVersao($pagina, $versao, $admin);
        $this->audit->log($request, 'Edição de Conteúdo do Site', 'Página "'.$pagina.'" voltou a uma versão anterior ('.$versao.').');

        return redirect('/admin/paginas-do-site?pagina='.$pagina)
            ->with('avisoDoSite', 'A versão anterior voltou ao ar. O que estava publicado ficou guardado no histórico.');
    }

    public function salvar(Request $request, string $pagina): RedirectResponse
    {
        abort_unless(config('features.gestaoConteudoSite') === true, 404);

        $esquema = $this->paginas->schema();
        abort_unless(array_key_exists($pagina, $esquema), 404);
        $definicao = $esquema[$pagina];

        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $topo = [];
        $enviadoTopo = $request->input('topo');
        foreach ($definicao['header'] as $campo) {
            $valor = is_array($enviadoTopo) ? ($enviadoTopo[$campo['key']] ?? null) : null;
            $topo[$campo['key']] = is_string($valor) ? $valor : '';
        }
        $itens = $this->itens($request->input('itens'), [], $definicao['item']);
        $acao = $request->string('acao')->toString();

        // Ações de rascunho: não gravam nada.
        if ($acao === 'adicionar') {
            if (count($itens) >= $definicao['maxItems']) {
                return $this->rascunho($pagina, $topo, $itens)->withErrors([
                    'itens' => 'Esta página aceita no máximo '.$definicao['maxItems'].' '.mb_strtolower($definicao['itemsLabel']).'.',
                ]);
            }
            $novo = ['id' => 'novo-'.(int) round(microtime(true) * 1000)];
            foreach ($definicao['item'] as $campo) {
                $novo[$campo['key']] = '';
            }
            $itens[] = $novo;

            return $this->rascunho($pagina, $topo, $itens);
        }
        if (preg_match('/^(subir|descer|remover):(\d+)$/', $acao, $partes) === 1) {
            $i = (int) $partes[2];
            if ($partes[1] === 'remover') {
                unset($itens[$i]);
            } else {
                $j = $partes[1] === 'subir' ? $i - 1 : $i + 1;
                if (isset($itens[$i], $itens[$j])) {
                    [$itens[$i], $itens[$j]] = [$itens[$j], $itens[$i]];
                }
            }

            return $this->rascunho($pagina, $topo, array_values($itens));
        }

        $this->paginas->upsert($pagina, array_merge($topo, ['items' => $itens]), $admin);
        $this->audit->log($request, 'Edição de Conteúdo do Site', 'Página "'.$pagina.'" foi atualizada.');

        return redirect('/admin/paginas-do-site?pagina='.$pagina)
            ->with('avisoDoSite', 'Conteúdo da página salvo e publicado no site.');
    }

    /**
     * Os itens do formulário (ou, sem formulário, os gravados), reduzidos aos
     * campos do schema.
     *
     * @param  list<array{key:string,label:string,type:string,maxLength:int}>  $campos
     * @return list<array<string,string>>
     */
    private function itens(mixed $enviados, mixed $gravados, array $campos): array
    {
        $fonte = is_array($enviados) ? $enviados : (is_array($gravados) ? $gravados : []);

        $lista = [];
        foreach ($fonte as $item) {
            if (! is_array($item)) {
                continue;
            }
            $linha = ['id' => is_string($item['id'] ?? null) ? $item['id'] : ''];
            foreach ($campos as $campo) {
                $linha[$campo['key']] = is_string($item[$campo['key']] ?? null) ? $item[$campo['key']] : '';
            }
            $lista[] = $linha;
        }

        return $lista;
    }

    /**
     * @param  array<string,string>  $topo
     * @param  list<array<string,string>>  $itens
     */
    private function rascunho(string $pagina, array $topo, array $itens): RedirectResponse
    {
        return redirect('/admin/paginas-do-site?pagina='.$pagina)->withInput(['topo' => $topo, 'itens' => $itens]);
    }
}
