<?php

use App\Http\Controllers\AppShellController;
use App\Http\Controllers\PaginaPublicaController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web — o roteador da migração para MPA (ADR 017)
|--------------------------------------------------------------------------
|
| A partir daqui o Laravel é a porta de entrada da aplicação, e não mais o
| Nginx servindo `dist/` com o Laravel pendurado em `/api` (ver DEPLOY_LARAVEL.md).
|
| A regra é uma só, e é esta tabela: **o que estiver registrado aqui é servido
| pelo Laravel; todo o resto cai no `fallback` e é entregue ao SPA React.**
| Migrar uma tela para Blade é acrescentar a rota dela acima do fallback — não
| há segunda lista a atualizar, nem interruptor a virar.
|
| Dois pontos de atenção enquanto a migração durar:
|
| 1. Toda rota acrescentada aqui precisa ter o prefixo em `rotas-laravel.json`,
|    senão ela funciona em produção e não em desenvolvimento (lá o Vite é quem
|    atende o navegador e só encaminha ao Laravel o que estiver naquela lista).
|    `RoteadorMpaTest` falha quando isso é esquecido.
| 2. As URLs não mudam (invariante 2 do plano 16): quem tem link salvo continua
|    chegando no mesmo lugar, migrado ou não.
*/

// Arquivos públicos de upload (materiais, imagens, vídeos de teste) — equivalente ao
// express.static('/uploads', ...) do Node legado. Sem autenticação, mesmo comportamento
// de antes; anti-traversal e checagem de existência ficam em UploadService::resolvePublicFile.
Route::get('/uploads/{filename}', [UploadController::class, 'servePublic'])
    ->where('filename', '[^\\/\\\\]+');

/*
 * Páginas institucionais do portal público — fase M1 do plano 16.
 *
 * A rota de prova da M0 (`/sistema/fundacao-mpa`) saiu daqui: ela existia para
 * demonstrar que uma rota registrada neste arquivo é atendida em Blade, e a
 * primeira tela de verdade passa a fazer essa demonstração sozinha.
 *
 * As URLs não mudam (invariante 2): `/o-ava` era servida pelo React e passa a
 * ser servida pelo Blade, no mesmo endereço. Quem tem o link salvo não percebe.
 */
Route::get('/o-ava', [PaginaPublicaController::class, 'oAva'])->name('publico.o-ava');

/*
 * Tudo que não foi migrado: o SPA React.
 *
 * `fallback` vale só para rotas web — um caminho inexistente sob `/api/*` continua
 * devolvendo o JSON de erro do contrato, e não o HTML do React.
 */
Route::fallback(AppShellController::class);
