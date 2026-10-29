<?php

use App\Http\Controllers\AlunosDoAdminController;
use App\Http\Controllers\AreaDoAlunoController;
use App\Http\Controllers\AreaDoInstrutorController;
use App\Http\Controllers\AutenticacaoWebController;
use App\Http\Controllers\ConfiguracoesDoAdminController;
use App\Http\Controllers\CursosDoAdminController;
use App\Http\Controllers\DadosGerenciaisAdminController;
use App\Http\Controllers\EquipeDoAdminController;
use App\Http\Controllers\ModelosDoAdminController;
use App\Http\Controllers\PaginaPublicaController;
use App\Http\Controllers\PaginasDoSiteAdminController;
use App\Http\Controllers\PainelDoAdminController;
use App\Http\Controllers\PerfilDoAdminController;
use App\Http\Controllers\PerfilDoAlunoController;
use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web (ADR 017, 020)
|--------------------------------------------------------------------------
|
| O Laravel é a única aplicação: todas as telas são Blade/Livewire e esta tabela
| é o roteador inteiro. O que não estiver registrado aqui é 404 (`errors/404`).
| Desde a fase M7 não há SPA, `dist/` nem `rotas-laravel.json`.
|
| As URLs não mudam (invariante 2 do plano 16): quem tem link salvo continua
| chegando no mesmo lugar.
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
 * Certificados — critérios e o autenticador público.
 *
 * Mesmo limitador da rota de API (`cert-lookup`): a consulta é a mesma, e as
 * duas portas dividirem o balde é o que impede varrer o banco de nomes pela
 * porta nova. Sem login, a chave só pode ser o IP.
 */
Route::get('/certificados', [PaginaPublicaController::class, 'certificados'])
    ->middleware('throttle:cert-lookup')
    ->name('publico.certificados');

/*
 * Calendário — a última página pública em React. A agenda vem dos encontros
 * realmente marcados, e não de conteúdo digitado (ver App\Support\AgendaAoVivo).
 */
Route::get('/calendario', [PaginaPublicaController::class, 'calendario'])->name('publico.calendario');

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

    /*
     * Certificados do aluno. O PDF passa pelo MESMO `CertificatePdfService`
     * da API, que já recusa certificado de outra pessoa; `?modo=ver` só troca
     * o `Content-Disposition` para o navegador abrir em vez de baixar.
     */
    Route::get('/aluno/certificados', [AreaDoAlunoController::class, 'certificados'])->name('aluno.certificados');
    Route::get('/aluno/certificados/{certificadoId}/pdf', [AreaDoAlunoController::class, 'pdfDoCertificado'])
        ->name('aluno.certificados.pdf');
    // Histórico escolar emitido pelo admin: aparece na lista de certificados e baixa aqui.
    Route::get('/aluno/historico/{historicoId}/pdf', [AreaDoAlunoController::class, 'pdfDoHistorico'])->whereNumber('historicoId');

    Route::get('/aluno/biblioteca', [AreaDoAlunoController::class, 'biblioteca'])->name('aluno.biblioteca');
    Route::get('/aluno/duvidas', [AreaDoAlunoController::class, 'ajuda'])->name('aluno.ajuda');

    /*
     * Suporte pedagógico: enviar uma dúvida ao gestor. É a função do cartão
     * que saiu da aula, e que ficou sem porta quando o menu passou a apontar
     * para a tela de LER mensagens (fechada para o aluno com `forum` desligado).
     */
    Route::get('/aluno/suporte', [AreaDoAlunoController::class, 'suporte'])->name('aluno.suporte');
    Route::post('/aluno/suporte', [AreaDoAlunoController::class, 'enviarSuporte']);

    /*
     * Perfil: dados da conta, nome e senha. As duas alterações conferem a
     * senha atual e usam o MESMO limitador da troca de senha da API
     * (`auth-password`, 5 por 15 min por usuário): conferir senha é o que um
     * ataque de força bruta procura, e as duas portas dividem o balde.
     */
    Route::get('/aluno/perfil', [PerfilDoAlunoController::class, 'mostrar'])->name('aluno.perfil');
    Route::post('/aluno/perfil/nome', [PerfilDoAlunoController::class, 'alterarNome'])->middleware('throttle:auth-password');
    Route::post('/aluno/perfil/senha', [PerfilDoAlunoController::class, 'alterarSenha'])->middleware('throttle:auth-password');
    Route::get('/aluno/curso/{cursoRef}', [AreaDoAlunoController::class, 'curso'])->name('aluno.curso');

    Route::get('/aluno/curso/{cursoRef}/aula/{aulaId}', [AreaDoAlunoController::class, 'aula'])->name('aluno.aula');

    /*
     * Avaliações: a lista e a prova. A prova é corrigida no FIM, no servidor,
     * e o gabarito não vai para a página (decisão de 20/10).
     */
    Route::get('/aluno/curso/{cursoRef}/avaliacoes', [AreaDoAlunoController::class, 'avaliacoes'])->name('aluno.avaliacoes');
    Route::get('/aluno/curso/{cursoRef}/avaliacoes/{avaliacao_id}', [AreaDoAlunoController::class, 'prova'])->name('aluno.prova');
    Route::post('/aluno/curso/{cursoRef}/avaliacoes/{avaliacao_id}', [AreaDoAlunoController::class, 'responderProva']);

    /*
     * As seções que existem no sistema mas estão desligadas por flag. O
     * servidor as atende para que NENHUM endereço de `/aluno` caia no React —
     * ver AreaDoAlunoController::secaoDesligada.
     */
    Route::get('/aluno/eventos', static fn (AreaDoAlunoController $c) => $c->secaoDesligada(
        'eventosWebinars',
        'Eventos e webinars',
        'A escola ainda não abriu eventos e webinars nesta plataforma.',
    ))->name('aluno.eventos');

    Route::get('/aluno/solicitacoes', static fn (AreaDoAlunoController $c) => $c->secaoDesligada(
        'solicitacoesAcademicas',
        'Solicitações',
        'O pedido de documentos pela plataforma ainda não está aberto; a coordenação do seu curso atende por outros meios.',
    ))->name('aluno.solicitacoes');

    Route::get('/aluno/mensagens', static fn (AreaDoAlunoController $c) => $c->secaoDesligada(
        'forum',
        'Mensagens',
        'A conversa com resposta dentro da plataforma ainda não está aberta. Você pode enviar uma dúvida ao gestor do curso, que responde pelos canais da escola.',
    ))->name('aluno.mensagens');

    Route::get('/aluno/curso/{cursoRef}/exercicios', static fn (AreaDoAlunoController $c) => $c->secaoDesligada(
        'atividadesPraticasAvancadas',
        'Exercícios práticos',
        'Os exercícios práticos ainda não estão abertos nesta plataforma.',
    ))->name('aluno.exercicios');

    Route::get('/aluno/curso/{cursoRef}/ao-vivo/{sessaoId}', static fn (AreaDoAlunoController $c) => $c->secaoDesligada(
        'liveClassroom',
        'Sala ao vivo',
        'A sala ao vivo dentro da plataforma ainda não está aberta. Quando há um encontro com link externo, ele aparece na tela do curso.',
    ))->name('aluno.ao-vivo');

    // Catálogo do aluno: a nova casa da matrícula, que saiu do painel junto com
    // "Disciplinas acadêmicas disponíveis".
    Route::get('/aluno/catalogo', [AreaDoAlunoController::class, 'catalogo'])->name('aluno.catalogo');
    Route::get('/aluno/catalogo/{cursoRef}', [AreaDoAlunoController::class, 'fichaDoCatalogo'])->name('aluno.catalogo.ficha');
    Route::post('/aluno/catalogo/{cursoRef}/matricula', [AreaDoAlunoController::class, 'matricular'])
        ->middleware('throttle:enrollment')
        ->name('aluno.matricular');

    /*
     * Qualquer outro endereço sob `/aluno` volta ao painel. REGISTRADA POR
     * ÚLTIMO: ela casa com tudo, e antes de outra rota engoliria a tela certa.
     *
     * Sem ela, `/aluno/qualquer-coisa` caía no React, que tratava seção
     * desconhecida como "painel" e desenhava o painel ANTIGO — e dali o aluno
     * navegava, por dentro do SPA, para as versões velhas de todas as telas já
     * migradas. É o que fecha a área do aluno: nenhum endereço dela é do React.
     */
    Route::get('/aluno/{resto}', static fn () => redirect('/aluno'))->where('resto', '.*');
});

/*
 * Área do instrutor (fase M4). Por enquanto só a escolha de curso: as seções
 * de dentro do curso continuam no React, e por isso `/inst` entra no contrato
 * de rotas como EXATO, não como prefixo — declarar o prefixo mandaria
 * `/inst/curso/...` ao Laravel, onde cairia no fallback que serve o React de
 * `dist/`, e a tela abriria em branco só na máquina de quem desenvolve.
 */
Route::middleware('instrutor.web')->group(function (): void {
    Route::get('/inst', [AreaDoInstrutorController::class, 'escolha'])->name('instrutor.escolha');

    // Curso novo pelo gestor. Fica ANTES de `/inst/{resto}` (o redirecionamento do fim do
    // arquivo) e longe de `/inst/curso/{cursoRef}`: `cursos` (plural) é a coleção.
    Route::get('/inst/cursos/novo', [AreaDoInstrutorController::class, 'formularioDeCurso'])->name('instrutor.curso.novo');
    Route::post('/inst/cursos', [AreaDoInstrutorController::class, 'criarCurso']);

    /*
     * A caixa de mensagens do gestor. Fica FORA de `/inst/curso/...` de
     * propósito: a dúvida do aluno chega de uma aula, mas quem responde quer
     * ver todas de uma vez, e não uma caixa por curso (decisão de 21/10).
     */
    Route::get('/inst/mensagens', [AreaDoInstrutorController::class, 'mensagens'])->name('instrutor.mensagens');
    Route::post('/inst/mensagens', [AreaDoInstrutorController::class, 'responder']);

    /*
     * Gestão do Curso. As seções de dentro do curso (grade, avaliações, alunos)
     * continuam no React, e por isso cada rota entra no contrato como EXATA.
     */
    Route::get('/inst/curso/{cursoRef}', [AreaDoInstrutorController::class, 'curso'])->name('instrutor.curso');
    Route::post('/inst/curso/{cursoRef}/propriedades', [AreaDoInstrutorController::class, 'salvarPropriedades']);
    Route::post('/inst/curso/{cursoRef}/vigencia', [AreaDoInstrutorController::class, 'salvarVigencia']);
    // Visibilidade para o aluno, capa e retirada do ar (ADR 029). A reativação é pelo ID do curso
    // (o retirado não tem tela própria) e fica junto de `/inst/cursos`.
    Route::post('/inst/curso/{cursoRef}/visibilidade', [AreaDoInstrutorController::class, 'alternarVisibilidade']);
    Route::post('/inst/curso/{cursoRef}/capa', [AreaDoInstrutorController::class, 'salvarCapa']);
    Route::post('/inst/curso/{cursoRef}/retirar', [AreaDoInstrutorController::class, 'retirarCurso']);
    Route::post('/inst/cursos/{cursoId}/reativar', [AreaDoInstrutorController::class, 'reativarCurso']);

    // Grade curricular: a lista, a ordem e a retirada. O EDITOR de aula é React
    // até a M6 e vive em `/inst/curso/{ref}/aula/...`, que não está aqui.
    Route::get('/inst/curso/{cursoRef}/grade-curricular', [AreaDoInstrutorController::class, 'grade'])->name('instrutor.grade');
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/mover', [AreaDoInstrutorController::class, 'moverAula']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/remover', [AreaDoInstrutorController::class, 'removerAula']);

    // A página de UMA aula (fase M6). Em `/aulas` (plural) e não em `/aula`: o
    // editor React segue em `/aula/{id}` até estas rotas cobrirem tudo, e dois
    // donos para o mesmo endereço deixariam a tela mudar conforme a máquina.
    Route::get('/inst/curso/{cursoRef}/aulas/nova', [AreaDoInstrutorController::class, 'formularioDeAula'])->name('instrutor.aula.nova');
    Route::post('/inst/curso/{cursoRef}/aulas', [AreaDoInstrutorController::class, 'criarAula']);
    Route::get('/inst/curso/{cursoRef}/aulas/{aulaId}', [AreaDoInstrutorController::class, 'aula'])->name('instrutor.aula');
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/identificacao', [AreaDoInstrutorController::class, 'salvarIdentificacao']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/video', [AreaDoInstrutorController::class, 'salvarVideo']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/material', [AreaDoInstrutorController::class, 'material']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/previa', [AreaDoInstrutorController::class, 'previa']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/versoes/{versao}/restaurar', [AreaDoInstrutorController::class, 'restaurarVersao']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/anexos', [AreaDoInstrutorController::class, 'anexarDocumento']);
    Route::post('/inst/curso/{cursoRef}/aulas/{aulaId}/anexos/{documentoId}/remover', [AreaDoInstrutorController::class, 'desvincularDocumento']);

    /*
     * Avaliações do curso. A de exercícios está desligada (`atividadesPraticasAvancadas`)
     * e responde pelo servidor, como a seção desligada do aluno.
     */
    Route::get('/inst/curso/{cursoRef}/avaliacoes', [AreaDoInstrutorController::class, 'avaliacoes'])->name('instrutor.avaliacoes');
    Route::get('/inst/curso/{cursoRef}/avaliacoes/nova', [AreaDoInstrutorController::class, 'formularioDeAvaliacao'])->name('instrutor.avaliacao.nova');
    Route::post('/inst/curso/{cursoRef}/avaliacoes', [AreaDoInstrutorController::class, 'salvarAvaliacao']);
    Route::get('/inst/curso/{cursoRef}/avaliacoes/exercicios', static fn (Request $r, string $cursoRef) => app(AreaDoInstrutorController::class)->secaoDesligada(
        $r,
        $cursoRef,
        'atividadesPraticasAvancadas',
        'Exercícios práticos',
        'Os exercícios práticos ainda não estão abertos nesta plataforma.',
    ))->name('instrutor.exercicios');
    Route::get('/inst/curso/{cursoRef}/avaliacoes/{avaliacao_id}/editar', [AreaDoInstrutorController::class, 'formularioDeAvaliacao'])->name('instrutor.avaliacao.editar');
    Route::post('/inst/curso/{cursoRef}/avaliacoes/{avaliacao_id}', [AreaDoInstrutorController::class, 'salvarAvaliacao']);
    Route::post('/inst/curso/{cursoRef}/avaliacoes/{avaliacao_id}/remover', [AreaDoInstrutorController::class, 'removerAvaliacao']);

    // Alunos do curso: a turma e a fila de matrículas. Com a aprovação automática
    // ligada (hoje) a fila não existe, e a tela mostra só a turma.
    Route::get('/inst/curso/{cursoRef}/alunos', [AreaDoInstrutorController::class, 'alunos'])->name('instrutor.alunos');
    Route::post('/inst/curso/{cursoRef}/pedidos/{pedidoId}', [AreaDoInstrutorController::class, 'decidirPedido']);

    /*
     * O editor de aula do painel React vivia em `/aula/{id}` e `/aula/nova`; agora
     * está em `/aulas/...` (acima). Os endereços antigos — que circulam em favoritos
     * e em links já enviados — levam ao novo, sem perder o curso nem a aula.
     */
    Route::get('/inst/curso/{cursoRef}/aula/nova', static fn (string $cursoRef) => redirect('/inst/curso/'.rawurlencode($cursoRef).'/aulas/nova'));
    Route::get('/inst/curso/{cursoRef}/aula/{aulaId}', static fn (string $cursoRef, string $aulaId) => redirect('/inst/curso/'.rawurlencode($cursoRef).'/aulas/'.rawurlencode($aulaId)));

    /*
     * O que sobrou de endereço antigo. REGISTRADAS POR ÚLTIMO: casam com muito, e
     * antes de outra rota engoliriam a tela certa.
     *
     * Sem elas, estes endereços caíam no React, que tratava seção desconhecida
     * como "Gestão do Curso" e desenhava o painel ANTIGO — e dali o instrutor
     * navegava, por dentro do SPA, às versões velhas das telas já migradas.
     *
     * Com o editor de aula em Blade (M6), `/inst` inteiro é do Laravel e é um
     * prefixo no contrato de rotas. A última rota casa com QUALQUER profundidade,
     * de propósito: um endereço sob `/inst` que o Laravel não atendesse cairia no
     * fallback, que serve o React — e o React o devolveria ao servidor, para sempre.
     */
    Route::get('/inst/curso/{cursoRef}/mensagens', static fn () => redirect('/inst/mensagens'));
    Route::get('/inst/curso/{cursoRef}/{resto}', static fn (string $cursoRef) => redirect('/inst/curso/'.rawurlencode($cursoRef)));
    Route::get('/inst/{resto}', static fn () => redirect('/inst'))->where('resto', '.*');
});

/*
 * Área do administrador (M5, plano 16). O painel migra por partes, então cada
 * rota entra no contrato (`rotas-laravel.json`) como EXATA: declarar `/admin`
 * como prefixo mandaria ao Laravel seções que ainda são React, e lá elas
 * cairiam no fallback.
 *
 * Fatia 1: a moldura (D §1) e os dois itens do menu do usuário que o painel
 * React não tinha — "Meu perfil" e "Trocar senha".
 * Fatia 2: Dashboard e os relatórios, só leitura.
 * Fatia 3: a Equipe Pedagógica (lista e busca).
 * Fatia 4: Cursos & Trilhas (prover, trocar responsável, tipo, vigência, capa, retirar do ar).
 */
Route::middleware('admin.web')->group(function (): void {
    Route::get('/admin/dados-gerenciais', [DadosGerenciaisAdminController::class, 'mostrar'])->name('admin.dados');
    // Mesmo limitador da API de exportação (`export`): são dados pessoais, em volume.
    Route::get('/admin/dados-gerenciais/baixar/{base}', [DadosGerenciaisAdminController::class, 'baixar'])->middleware('throttle:export');
    Route::get('/admin/paginas-do-site', [PaginasDoSiteAdminController::class, 'mostrar'])->name('admin.paginas');
    Route::post('/admin/paginas-do-site/{pagina}', [PaginasDoSiteAdminController::class, 'salvar']);
    Route::post('/admin/paginas-do-site/{pagina}/versoes/{versao}/restaurar', [PaginasDoSiteAdminController::class, 'restaurar']);
    Route::get('/admin/configuracoes', [ConfiguracoesDoAdminController::class, 'mostrar'])->name('admin.configuracoes');
    Route::post('/admin/configuracoes', [ConfiguracoesDoAdminController::class, 'salvar']);
    Route::get('/admin/templates', [ModelosDoAdminController::class, 'mostrar'])->name('admin.templates');
    Route::post('/admin/templates/{tipo}', [ModelosDoAdminController::class, 'salvar']);
    Route::post('/admin/templates/{tipo}/previa', [ModelosDoAdminController::class, 'previa']);
    Route::post('/admin/templates/{tipo}/logo', [ModelosDoAdminController::class, 'enviarLogo']);
    Route::post('/admin/templates/{tipo}/logo/tirar', [ModelosDoAdminController::class, 'tirarLogo']);
    Route::post('/admin/templates/{tipo}/versoes/{versao}/restaurar', [ModelosDoAdminController::class, 'restaurar']);
    Route::get('/admin/alunos', [AlunosDoAdminController::class, 'listar'])->name('admin.alunos');
    Route::get('/admin/alunos/{alunoId}', [AlunosDoAdminController::class, 'ficha'])->name('admin.aluno');
    Route::post('/admin/alunos', [AlunosDoAdminController::class, 'cadastrar']);
    // Mesmo limitador da rota de API de redefinição administrativa (`auth-password`).
    Route::post('/admin/alunos/{alunoId}/senha', [AlunosDoAdminController::class, 'redefinirSenha'])->middleware('throttle:auth-password');
    Route::post('/admin/alunos/{alunoId}/confirmar', [AlunosDoAdminController::class, 'confirmarCadastro']);
    Route::post('/admin/alunos/{alunoId}/matricula-multipla', [AlunosDoAdminController::class, 'matriculaMultipla']);
    Route::post('/admin/alunos/{alunoId}/historico', [AlunosDoAdminController::class, 'emitirHistorico']);
    Route::get('/admin/alunos/{alunoId}/historico/{historicoId}/pdf', [AlunosDoAdminController::class, 'pdfDoHistorico'])->whereNumber('historicoId');
    Route::post('/admin/alunos/{alunoId}/inativar', [AlunosDoAdminController::class, 'inativar']);
    Route::post('/admin/alunos/{alunoId}/reativar', [AlunosDoAdminController::class, 'reativar']);
    Route::get('/admin/cursos', [CursosDoAdminController::class, 'listar'])->name('admin.cursos');
    Route::post('/admin/cursos', [CursosDoAdminController::class, 'criar']);
    Route::post('/admin/cursos/{cursoId}/instrutor', [CursosDoAdminController::class, 'trocarInstrutor']);
    Route::post('/admin/cursos/{cursoId}/propriedades', [CursosDoAdminController::class, 'salvarPropriedades']);
    Route::post('/admin/cursos/{cursoId}/vigencia', [CursosDoAdminController::class, 'salvarVigencia']);
    Route::post('/admin/cursos/{cursoId}/capa', [CursosDoAdminController::class, 'salvarCapa']);
    Route::post('/admin/cursos/{cursoId}/retirar', [CursosDoAdminController::class, 'retirar']);
    Route::post('/admin/cursos/{cursoId}/reativar', [CursosDoAdminController::class, 'reativar']);
    Route::get('/admin/equipe', [EquipeDoAdminController::class, 'listar'])->name('admin.equipe');
    Route::post('/admin/equipe/{gestorId}/reativar', [EquipeDoAdminController::class, 'reativar']);
    Route::post('/admin/equipe', [EquipeDoAdminController::class, 'criar']);
    Route::post('/admin/equipe/{gestorId}/acesso', [EquipeDoAdminController::class, 'acesso'])->middleware('throttle:auth-password');
    Route::post('/admin/equipe/{gestorId}/cursos', [EquipeDoAdminController::class, 'cursos']);
    Route::post('/admin/equipe/{gestorId}/retirar', [EquipeDoAdminController::class, 'retirar']);
    Route::get('/admin', [PainelDoAdminController::class, 'dashboard'])->name('admin.painel');
    Route::get('/admin/relatorio/{sub}', [PainelDoAdminController::class, 'dashboard'])->name('admin.relatorio');

    Route::get('/admin/perfil', [PerfilDoAdminController::class, 'perfil'])->name('admin.perfil');
    Route::get('/admin/perfil/senha', [PerfilDoAdminController::class, 'formularioDeSenha'])->name('admin.senha');
    Route::post('/admin/perfil/senha', [PerfilDoAdminController::class, 'alterarSenha'])->middleware('throttle:auth-password');

    /*
     * As seções que existem no sistema mas estão desligadas por flag. O servidor
     * as atende para que NENHUM endereço de `/admin` caia no React — ver
     * PainelDoAdminController::secaoDesligada. Ligada a flag, a rota responde 501
     * e `SecoesDesligadasDoAdminTest` falha apontando qual tela construir.
     */
    Route::get('/admin/exercicios', static fn (PainelDoAdminController $c) => $c->secaoDesligada('atividadesPraticasAvancadas', 'exercicios', 'A escola ainda não abriu os exercícios práticos nesta plataforma.'))->name('admin.exercicios');
    Route::get('/admin/documentos', static fn (PainelDoAdminController $c) => $c->secaoDesligada('solicitacoesAcademicas', 'requests', 'A escola ainda não abriu as solicitações acadêmicas nesta plataforma.'))->name('admin.documentos');

    // O que sobrar de `/admin` volta ao Dashboard. Sem isto, o fallback serviria o React.
    Route::get('/admin/{resto}', static fn () => redirect('/admin'))->where('resto', '.*');
});

/*
 * Sem fallback: o que não casa com rota nenhuma é 404 de verdade (view
 * `errors/404`). Não há mais SPA para onde um endereço desconhecido cair
 * (fase M7, ADR 020). Um caminho inexistente sob `/api/*` continua devolvendo o
 * JSON de erro do contrato.
 */
