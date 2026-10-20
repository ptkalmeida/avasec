<?php

use App\Http\Controllers\AppShellController;
use App\Http\Controllers\AreaDoAlunoController;
use App\Http\Controllers\AutenticacaoWebController;
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
/*
 * A raiz. Era a última tela do portal público ainda servida pelo React, e
 * esperava o login existir como endereço: enquanto o modal de entrar morasse
 * dentro do SPA em `/`, migrar `/` deixaria o sistema sem porta de entrada.
 */
Route::get('/', [PaginaPublicaController::class, 'inicio'])->name('publico.inicio');

Route::get('/o-ava', [PaginaPublicaController::class, 'oAva'])->name('publico.o-ava');
Route::get('/duvidas', [PaginaPublicaController::class, 'duvidas'])->name('publico.duvidas');
Route::get('/o-projeto', [PaginaPublicaController::class, 'oProjeto'])->name('publico.o-projeto');
Route::get('/orientacoes', [PaginaPublicaController::class, 'orientacoes'])->name('publico.orientacoes');

/*
 * Notícias — fechou o grupo "A Escola" em Blade. A busca do cabeçalho aponta
 * para cá desde que ele virou Blade (M2), e até aqui o link caía no React.
 */
Route::get('/noticias', [PaginaPublicaController::class, 'noticias'])->name('publico.noticias');

/*
 * Catálogo público — primeira tela da fase M2, e a primeira do redesenho das
 * cinco telas (plano 18). A URL não muda: `/cursos` era servida pelo React e
 * passa a ser servida pelo Blade, no mesmo endereço.
 */
Route::get('/cursos', [PaginaPublicaController::class, 'cursos'])->name('publico.cursos');

/*
 * Área do aluno — fase M3, telas 3, 4 e 5 do redesenho (plano 18).
 *
 * `aluno.web` lê o MESMO cookie JWT que a API já usa; o que muda é o destino de
 * quem não passa — o login, e não um JSON de erro. O fluxo de autenticação não
 * foi tocado (ver App\Http\Middleware\AreaDoAluno).
 *
 * As rotas são declaradas uma a uma, e não como grupo com prefixo: `/aluno` é
 * uma árvore de dez telas e só três migraram. O que não está aqui continua
 * caindo no fallback e sendo servido pelo React — e `rotas-laravel.json` as
 * declara em "exatos" para que o proxy de desenvolvimento faça o mesmo recorte.
 */
/*
 * Entrar, cadastrar-se e sair — fase M2, com validação humana explícita de
 * 19/10/2026 para mexer no fluxo de autenticação (AGENTS.md).
 *
 * O mecanismo NÃO mudou: mesmo JWT, mesmo cookie, mesmo `AuthService`, mesmos
 * limitadores de taxa (`throttle:auth-login` e `auth-register` são os mesmos
 * baldes da API — um ataque não ganha o dobro de tentativas por existirem duas
 * portas). O que mudou é que a porta passou a ser um endereço.
 *
 * Sair é POST, nunca GET: um `<img src="/sair">` numa página qualquer deslogaria
 * quem a abrisse, e o token CSRF é o que impede isso.
 */
Route::get('/entrar', [AutenticacaoWebController::class, 'mostrarLogin'])->name('auth.entrar');
Route::post('/entrar', [AutenticacaoWebController::class, 'entrar'])->middleware('throttle:auth-login');
Route::get('/cadastro', [AutenticacaoWebController::class, 'mostrarCadastro'])->name('auth.cadastro');
Route::post('/cadastro', [AutenticacaoWebController::class, 'cadastrar'])->middleware('throttle:auth-register');
Route::post('/sair', [AutenticacaoWebController::class, 'sair'])->name('auth.sair');

/*
 * `/sair` no GET existe só para não cair no fallback.
 *
 * Sem esta linha, abrir `/sair` na barra de endereço entregava o casco do React
 * com 200 — o fallback engolindo um caminho que É do servidor, que é a mesma
 * armadilha registrada na M0 com `/uploads/../../.env`. Ela não desloga
 * ninguém, de propósito: encerrar sessão por GET deixaria um `<img src="/sair">`
 * deslogar quem abrisse a página.
 */
Route::get('/sair', static fn () => redirect('/'));

Route::middleware('aluno.web')->group(function (): void {
    Route::get('/aluno', [AreaDoAlunoController::class, 'painel'])->name('aluno.painel');
    Route::get('/aluno/curso/{cursoRef}', [AreaDoAlunoController::class, 'curso'])->name('aluno.curso');

    Route::get('/aluno/curso/{cursoRef}/aula/{aulaId}', [AreaDoAlunoController::class, 'aula'])->name('aluno.aula');

    // Catálogo do aluno: a nova casa da matrícula, que saiu do painel junto com
    // "Disciplinas acadêmicas disponíveis".
    Route::get('/aluno/catalogo', [AreaDoAlunoController::class, 'catalogo'])->name('aluno.catalogo');
    Route::get('/aluno/catalogo/{cursoRef}', [AreaDoAlunoController::class, 'fichaDoCatalogo'])->name('aluno.catalogo.ficha');
    Route::post('/aluno/catalogo/{cursoRef}/matricula', [AreaDoAlunoController::class, 'matricular'])
        ->middleware('throttle:enrollment')
        ->name('aluno.matricular');
});

/*
 * Tudo que não foi migrado: o SPA React.
 *
 * `fallback` vale só para rotas web — um caminho inexistente sob `/api/*` continua
 * devolvendo o JSON de erro do contrato, e não o HTML do React.
 */
Route::fallback(AppShellController::class);
