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
    public function __construct(private readonly SitePageContentService $conteudo) {}

    public function oAva(): View
    {
        return view('publico.o-ava', ['pagina' => $this->conteudo->get('o-ava')]);
    }
}
