<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Entrega o SPA React para toda rota que ainda NÃO migrou para Blade.
 *
 * É a metade React do roteador da migração (ADR 017, plano 16). A outra metade é
 * a própria tabela de rotas do Laravel: o que estiver registrado lá é servido em
 * Blade, e **tudo o mais cai aqui**. Não existe lista de "rotas ainda não
 * migradas" — ela seria uma segunda fonte de verdade, fadada a divergir da
 * primeira. Migrar uma tela é só registrar a rota; ela deixa de cair neste
 * fallback no mesmo instante, sem nenhum outro arquivo para lembrar de editar.
 *
 * Em produção o Nginx tem a raiz em `backend-laravel/public`, e os arquivos do
 * build (`/assets/...`) são servidos direto do `dist/` — ver DEPLOY_LARAVEL.md.
 * Em desenvolvimento esta rota quase nunca é exercitada, porque o Vite (:5173) é
 * a porta de entrada do navegador e serve o React com HMR.
 */
final class AppShellController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Caminho de recurso do servidor que não casou com rota nenhuma é 404 de
        // verdade — não "o SPA resolve". Ver a justificativa em config/mpa.php:
        // sem isto, `/uploads/../../.env` passava a responder 200 com a página do
        // React, escondendo uma recusa que é proposital.
        if ($this->ehCaminhoDoServidor($request)) {
            abort(404);
        }

        $indice = $this->caminhoDoIndice();

        if ($indice === null) {
            // Sem build, o honesto é dizer isso — e não devolver 404, que faria
            // parecer que a rota não existe, nem 500, que faria parecer defeito.
            return response()->view('sistema.sem-build', [], 503);
        }

        // `file_get_contents` e não `response()->file()`: o conteúdo é servido como
        // HTML de uma rota, não como download de arquivo estático.
        return response((string) file_get_contents($indice), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** O caminho pedido pertence a um prefixo do servidor (API, uploads, saúde…)? */
    private function ehCaminhoDoServidor(Request $request): bool
    {
        /** @var list<string> $prefixos */
        $prefixos = config('mpa.prefixos_do_servidor', []);

        foreach ($prefixos as $prefixo) {
            $limpo = trim($prefixo, '/');

            if ($limpo !== '' && $request->is($limpo, $limpo.'/*')) {
                return true;
            }
        }

        return false;
    }

    /** Caminho do `dist/index.html` gerado por `npm run build`, ou null. */
    private function caminhoDoIndice(): ?string
    {
        $indice = base_path('../dist/index.html');

        return is_file($indice) ? $indice : null;
    }
}
