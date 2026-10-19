<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SitePageContentService;
use Illuminate\View\View;

/**
 * Páginas institucionais do portal público, em Blade (fase M1 do plano 16).
 *
 * O conteúdo vem do MESMO `SitePageContentService` que a API já servia ao React
 * — mesma chave de página, mesmos padrões de fábrica, mesma edição pelo admin.
 * Não há segunda fonte: migrar a tela não muda o que ela mostra, que é a
 * invariante 1 do plano 16.
 */
final class PaginaPublicaController extends Controller
{
    /** Quantos cursos a vitrine mostra. Decisão da skill, não acaso de layout. */
    private const CURSOS_EM_DESTAQUE = 4;

    public function __construct(private readonly SitePageContentService $conteudo) {}

    public function oAva(): View
    {
        return view('publico.o-ava', ['pagina' => $this->conteudo->get('o-ava')]);
    }

    public function duvidas(): View
    {
        return view('publico.duvidas', ['pagina' => $this->conteudo->get('duvidas')]);
    }

    /**
     * A chave do conteúdo é `o-projeto` e a URL também — diferente do que o
     * nome do método sugere. Mantido assim porque a URL é contrato (invariante
     * 2 do plano 16): quem tem o link salvo chega no mesmo lugar.
     */
    public function oProjeto(): View
    {
        return view('publico.o-projeto', ['pagina' => $this->conteudo->get('o-projeto')]);
    }

    public function orientacoes(): View
    {
        return view('publico.orientacoes', ['pagina' => $this->conteudo->get('orientacoes')]);
    }

    /**
     * Catálogo público — quatro cursos, sem busca e sem filtros.
     *
     * A fonte é `config/catalogo_publico.php`, e não a tabela `Course`: a
     * vitrine do portal nunca leu o banco, e trocar isso aqui mudaria o que a
     * tela mostra (invariante 1 do plano 16). O arquivo de configuração traz o
     * levantamento completo e a pendência.
     *
     * Quatro é decisão da skill `redesenhar-ava-lms`, aprovada em 19/10/2026.
     * São os quatro primeiros, e a tela não afirma que são os mais recentes —
     * não há data de publicação na vitrine para sustentar a afirmação.
     */
    public function cursos(): View
    {
        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');

        return view('publico.cursos', ['cursos' => array_slice($vitrine, 0, self::CURSOS_EM_DESTAQUE)]);
    }
}
