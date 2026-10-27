<?php

declare(strict_types=1);

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitePageContentController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebinarController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Etapa 0 da migração: rota de saúde (valida boot + conexão MySQL + proxy do Node).
Route::get('/health-laravel', [HealthController::class, 'show']);

// Etapa 2 (autenticação): espelha src/server/routes/authRoutes.ts. O Laravel passa a
// EMITIR o JWT (mesmo formato/segredo do Node) e a gerir o cookie ava_session.
// Rate limiters por rota reproduzem os limites do Node (ver RouteServiceProvider).
Route::prefix('autenticacao')->group(function (): void {
    Route::post('/cadastro', [AuthController::class, 'register'])
        ->middleware(['throttle:auth-register', 'jwt.optional']);
    Route::post('/entrar', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');
    Route::get('/eu', [AuthController::class, 'me'])
        ->middleware('jwt');
    Route::post('/sair', [AuthController::class, 'logout']);
    // `active`: conta bloqueada, inativada ou pendente não troca a própria senha com um token ainda válido.
    Route::put('/senha', [AuthController::class, 'changePassword'])
        ->middleware(['throttle:auth-password', 'jwt', 'active']);

    // Aluno nunca lista usuários (vazava e-mails de todos, inclusive admins). Instrutor
    // fica restrito aos próprios alunos no controller; admin é irrestrito.
    Route::get('/usuarios', [AuthController::class, 'listUsers'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/usuarios/{id}/status', [AuthController::class, 'updateStatus'])
        ->middleware(['jwt', 'active', 'role:admin']);
    // Rename seguro (ADR 10): self ou admin — identidade é o id, o nome é display.
    Route::put('/usuarios/{id}/nome', [AuthController::class, 'renameUser'])
        ->middleware(['jwt', 'active']);
    // Redefinição administrativa: sem a senha atual (o admin não a conhece), por isso
    // restrita a admin e auditada. A tela de gestão oferecia esta ação desde sempre,
    // mas sem endpoint: só alterava estado local, dando à coordenação a impressão
    // falsa de ter revogado um acesso.
    Route::put('/usuarios/{id}/senha', [AuthController::class, 'adminResetPassword'])
        ->middleware(['throttle:auth-password', 'jwt', 'active', 'role:admin']);
    Route::delete('/usuarios/{id}', [AuthController::class, 'removeUser'])
        ->middleware(['jwt', 'active', 'role:admin']);
});

// Etapa 1 (módulo piloto): Biblioteca Digital e Eventos/Webinars.
// Contrato idêntico ao Node (src/server/routes/catalogRoutes.ts): GET público,
// POST restrito a instrutor/admin, cada rota atrás da sua feature flag.
Route::middleware('feature:materiaisComplementares')->group(function (): void {
    Route::get('/biblioteca', [LibraryController::class, 'index'])->middleware('jwt.optional');
    Route::post('/biblioteca', [LibraryController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

Route::middleware('feature:eventosWebinars')->group(function (): void {
    Route::get('/webinars', [WebinarController::class, 'index'])->middleware('jwt.optional');
    Route::post('/webinars', [WebinarController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    // Exclusao existe para a area de gestao de webinars poder desmarcar um evento;
    // antes so havia criar e listar, e um webinar agendado por engano ficava na
    // agenda publica para sempre.
    Route::delete('/webinars/{id}', [WebinarController::class, 'destroy'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 (núcleo de negócio) — Cursos. Espelha src/server/routes/courseRoutes.ts:
// catálogo GET público; mutações restritas a instrutor/admin (ownership no service).
Route::middleware('feature:catalogoCursos')->group(function (): void {
    // Catálogo público (escopo declarado), mas com `jwt.optional`: se houver sessão,
    // o material de estudo das aulas do próprio aluno/instrutor vem junto; sem sessão,
    // só a vitrine. Antes desta linha o curso inteiro — texto das aulas, url_video,
    // documentos e link do Meet — saía para qualquer visitante.
    Route::get('/cursos', [CourseController::class, 'index'])
        ->middleware('jwt.optional');
    // ANTES de qualquer rota com parâmetro: 'resolve' é segmento literal e não
    // pode ser confundido com um slug de curso.
    Route::get('/cursos/resolver/{valor}', [CourseController::class, 'resolve'])
        ->middleware('jwt.optional')
        ->where('valor', '[A-Za-z0-9_-]+');
    Route::post('/cursos', [CourseController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/cursos/{id}', [CourseController::class, 'update'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/cursos/{id}', [CourseController::class, 'destroy'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 — Progresso (flag progresso).
Route::middleware(['feature:progresso', 'jwt', 'active'])->group(function (): void {
    Route::get('/progresso', [EnrollmentController::class, 'getProgress']);
    Route::post('/progresso', [EnrollmentController::class, 'upsertProgress'])
        ->middleware('role:student,admin');
});

// Etapa 3 — Matrículas e Admissões (flag matricula).
Route::middleware('feature:matricula')->group(function (): void {
    Route::get('/matriculas', [EnrollmentController::class, 'getEnrollments'])
        ->middleware(['jwt', 'active']);
    Route::post('/matriculas/proprio/matricular', [EnrollmentController::class, 'selfEnroll'])
        ->middleware(['throttle:enrollment', 'jwt', 'active', 'role:student']);
    Route::post('/matriculas/proprio/desistir', [EnrollmentController::class, 'selfDrop'])
        ->middleware(['jwt', 'active', 'role:student']);
    Route::post('/matriculas/proprio/concluir', [EnrollmentController::class, 'selfComplete'])
        ->middleware(['jwt', 'active', 'role:student']);
    // Identidade por FK (ADR 10): o recurso é endereçado pelo usuario_id do aluno.
    Route::put('/matriculas/{usuario_id}', [EnrollmentController::class, 'upsertEnrollment'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/ingressos', [EnrollmentController::class, 'listAdmissions'])
        ->middleware(['jwt', 'active']);
    Route::post('/ingressos', [EnrollmentController::class, 'createAdmission'])
        ->middleware(['throttle:enrollment', 'jwt', 'active', 'role:student,admin']);
    Route::put('/ingressos/{id}', [EnrollmentController::class, 'updateAdmissionStatus'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 — Certificados (flag certificados). Verificação pública; emissão/listagem
// autenticadas; exclusão só admin. Espelha src/server/routes/certificateRoutes.ts.
Route::middleware('feature:certificados')->group(function (): void {
    Route::get('/certificados/verificar', [CertificateController::class, 'verify'])
        ->middleware('throttle:cert-lookup');
    Route::get('/certificados', [CertificateController::class, 'index'])
        ->middleware(['jwt', 'active']);
    Route::get('/certificados/{id}/pdf', [CertificateController::class, 'pdf'])
        ->middleware(['jwt', 'active']);
    Route::post('/certificados', [CertificateController::class, 'store'])
        ->middleware(['jwt', 'active']);
    Route::delete('/certificados/{id}', [CertificateController::class, 'destroy'])
        ->middleware(['jwt', 'active', 'role:admin']);
});

// Etapa 4 — Uploads (flag uploadArquivos). Espelha src/server/upload.ts: upload com
// validação de magic bytes; download privado autorizado (dono/instrutor/admin).
// Arquivos PÚBLICOS continuam servidos pela rota estática /uploads do Node (origem única).
Route::middleware(['feature:uploadArquivos', 'jwt', 'active'])->group(function (): void {
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:upload');
    Route::get('/arquivos/{id}', [UploadController::class, 'download']);
});

// ---------------- Etapa 5 — módulos restantes ----------------

// Quizzes e submissões (flag quizSimples).
Route::middleware('feature:quizSimples')->group(function (): void {
    // Exige autenticação, e o ALUNO não recebe o gabarito (indice_opcao_correta,
    // explanation, mensagem_revisao) — só instrutor e admin. A nota é sempre
    // recalculada no servidor (submitQuiz).
    Route::get('/avaliacoes', [LearningController::class, 'listQuizzes'])->middleware(['jwt', 'active']);
    Route::post('/avaliacoes', [LearningController::class, 'createQuiz'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/avaliacoes/{id}', [LearningController::class, 'deleteQuiz'])->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/tentativas-avaliacao', [LearningController::class, 'listQuizSubmissions'])->middleware(['jwt', 'active']);
    Route::post('/tentativas-avaliacao', [LearningController::class, 'submitQuiz'])->middleware(['jwt', 'active', 'role:student']);
});

// Fórum (flag forum).
Route::middleware(['feature:forum', 'jwt', 'active'])->group(function (): void {
    Route::get('/forum', [LearningController::class, 'listForumMessages']);
    Route::post('/forum', [LearningController::class, 'createForumMessage']);
    Route::put('/forum/{id}/curtir', [LearningController::class, 'toggleForumLike']);
    Route::delete('/forum/{id}', [LearningController::class, 'deleteForumMessage']);
});

// Exercícios práticos e entregas (flag atividadesPraticasAvancadas).
Route::middleware('feature:atividadesPraticasAvancadas')->group(function (): void {
    Route::get('/exercicios', [LearningController::class, 'listExercises'])->middleware(['jwt', 'active']);
    Route::post('/exercicios', [LearningController::class, 'createExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/exercicios/{id}', [LearningController::class, 'updateExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/exercicios/{id}', [LearningController::class, 'deleteExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/entregas-exercicio', [LearningController::class, 'listExerciseSubmissions'])->middleware(['jwt', 'active']);
    Route::post('/entregas-exercicio', [LearningController::class, 'submitExercise'])->middleware(['jwt', 'active', 'role:student']);
    Route::put('/entregas-exercicio/{id}/corrigir', [LearningController::class, 'gradeSubmission'])->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Chat de aula ao vivo (flag liveClassroom).
Route::middleware(['feature:liveClassroom', 'jwt', 'active'])->group(function (): void {
    Route::get('/chat', [MessagingController::class, 'listChatMessages']);
    Route::post('/chat', [MessagingController::class, 'createChatMessage']);
});

// Mensagens diretas (flag mensagensDiretas).
Route::middleware(['feature:mensagensDiretas', 'jwt', 'active'])->group(function (): void {
    Route::get('/mensagens-diretas', [MessagingController::class, 'listDirectMessages']);
    Route::post('/mensagens-diretas', [MessagingController::class, 'createDirectMessage']);
});

// Solicitações acadêmicas (flag solicitacoesAcademicas).
Route::middleware('feature:solicitacoesAcademicas')->group(function (): void {
    Route::get('/solicitacoes-academicas', [RequestController::class, 'index'])->middleware(['jwt', 'active']);
    Route::post('/solicitacoes-academicas', [RequestController::class, 'store'])->middleware(['throttle:justification', 'jwt', 'active', 'role:student,admin']);
    Route::put('/solicitacoes-academicas/{id}', [RequestController::class, 'updateStatus'])->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Configurações do sistema (sem feature flag). GET público; PUT só admin.
Route::get('/configuracoes-sistema', [SettingsController::class, 'show']);
Route::put('/configuracoes-sistema', [SettingsController::class, 'update'])->middleware(['jwt', 'active', 'role:admin']);

// Templates de documentos (certificado, histórico) — leitura autenticada (o aluno
// precisa ver os mesmos dados institucionais/assinaturas na prévia do próprio
// certificado); edição só Admin Superior (sem feature flag: ferramenta administrativa).
Route::prefix('modelos-documento')->middleware(['jwt', 'active'])->group(function (): void {
    Route::get('/{tipo}', [DocumentTemplateController::class, 'show']);
    Route::put('/{tipo}', [DocumentTemplateController::class, 'update'])->middleware('role:admin');
    Route::get('/{tipo}/previa', [DocumentTemplateController::class, 'preview'])->middleware('role:admin');
});

// Conteúdo das páginas públicas do portal. GET é público sem autenticação: o
// visitante anônimo monta o site com isso (mesma forma de /system-settings).
// PUT é só Admin Superior — o service repete a checagem de papel.
Route::prefix('conteudo-site')->middleware('feature:gestaoConteudoSite')->group(function (): void {
    Route::get('/', [SitePageContentController::class, 'index']);
    Route::get('/{chave_pagina}', [SitePageContentController::class, 'show']);
    Route::put('/{chave_pagina}', [SitePageContentController::class, 'update'])
        ->middleware(['jwt', 'active', 'role:admin']);
});

// Auditoria (LogSeguranca) — só leitura/limpeza por admin; nunca há POST (gravação é server-side).
Route::prefix('logs-seguranca')->middleware(['jwt', 'active', 'role:admin'])->group(function (): void {
    Route::get('/', [AuditController::class, 'listSecurityLogs']);
    Route::delete('/', [AuditController::class, 'clearSecurityLogs']);
});

// Telemetria (EventoCliente) — POST aceita anônimo (identidade vem do token se houver); GET só admin.
// Rota pública que ESCREVE no banco (EventoCliente): precisa de limite, senão qualquer
// anônimo enfileira linhas com texto próprio até encher a tabela.
Route::post('/telemetria', [AuditController::class, 'recordClientEvent'])
    ->middleware(['throttle:telemetry', 'jwt.optional']);
Route::get('/telemetria', [AuditController::class, 'listClientEvents'])->middleware(['jwt', 'active', 'role:admin']);

// Exportação de Dados Gerenciais (flag dadosGerenciais) — só admin, com rate limit e auditoria.
Route::middleware('feature:dadosGerenciais')->group(function (): void {
    Route::get('/exportar/{dataset}', [ExportController::class, 'show'])
        ->middleware(['throttle:export', 'jwt', 'active', 'role:admin']);
});

/*
 * Caminho inexistente sob /api/* devolve o erro do contrato, não o HTML do React.
 *
 * Desde que o Laravel virou a porta de entrada (ADR 017), o `fallback` de
 * routes/web.php captura tudo que nenhuma rota atendeu — e isso incluía
 * `/api/qualquer-coisa`, que passou a responder 200 com a página do React.
 * Todo cliente da API receberia uma página HTML no lugar de um erro, e um 404
 * de digitação viraria "deu certo". Este fallback, registrado DENTRO do grupo
 * da API, intercepta antes e mantém o formato de erro de sempre.
 *
 * Pego por EnderecoDesconhecidoTest::test_caminho_inexistente_na_api_devolve_json.
 */
Route::fallback(function (): JsonResponse {
    return response()->json([
        'error' => true,
        'code' => 'NOT_FOUND',
        'message' => 'Recurso não encontrado.',
    ], 404);
});
