<?php

declare(strict_types=1);

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebinarController;
use Illuminate\Support\Facades\Route;

// Etapa 0 da migração: rota de saúde (valida boot + conexão MySQL + proxy do Node).
Route::get('/health-laravel', [HealthController::class, 'show']);

// Etapa 2 (autenticação): espelha src/server/routes/authRoutes.ts. O Laravel passa a
// EMITIR o JWT (mesmo formato/segredo do Node) e a gerir o cookie ava_session.
// Rate limiters por rota reproduzem os limites do Node (ver RouteServiceProvider).
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware(['throttle:auth-register', 'jwt.optional']);
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');
    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('jwt');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'changePassword'])
        ->middleware(['throttle:auth-password', 'jwt']);

    // Aluno nunca lista usuários (vazava e-mails de todos, inclusive admins). Instrutor
    // fica restrito aos próprios alunos no controller; admin é irrestrito.
    Route::get('/users', [AuthController::class, 'listUsers'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/users/{id}/status', [AuthController::class, 'updateStatus'])
        ->middleware(['jwt', 'active', 'role:admin']);
    // Rename seguro (ADR 10): self ou admin — identidade é o id, o nome é display.
    Route::put('/users/{id}/name', [AuthController::class, 'renameUser'])
        ->middleware(['jwt', 'active']);
    Route::delete('/users/{id}', [AuthController::class, 'removeUser'])
        ->middleware(['jwt', 'active', 'role:admin']);
});

// Etapa 1 (módulo piloto): Biblioteca Digital e Eventos/Webinars.
// Contrato idêntico ao Node (src/server/routes/catalogRoutes.ts): GET público,
// POST restrito a instrutor/admin, cada rota atrás da sua feature flag.
Route::middleware('feature:materiaisComplementares')->group(function (): void {
    Route::get('/library', [LibraryController::class, 'index']);
    Route::post('/library', [LibraryController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

Route::middleware('feature:eventosWebinars')->group(function (): void {
    Route::get('/webinars', [WebinarController::class, 'index']);
    Route::post('/webinars', [WebinarController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 (núcleo de negócio) — Cursos. Espelha src/server/routes/courseRoutes.ts:
// catálogo GET público; mutações restritas a instrutor/admin (ownership no service).
Route::middleware('feature:catalogoCursos')->group(function (): void {
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/courses/{id}', [CourseController::class, 'update'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 — Progresso (flag progresso).
Route::middleware(['feature:progresso', 'jwt', 'active'])->group(function (): void {
    Route::get('/progress', [EnrollmentController::class, 'getProgress']);
    Route::post('/progress', [EnrollmentController::class, 'upsertProgress'])
        ->middleware('role:student,admin');
});

// Etapa 3 — Matrículas e Admissões (flag matricula).
Route::middleware('feature:matricula')->group(function (): void {
    Route::get('/enrollments', [EnrollmentController::class, 'getEnrollments'])
        ->middleware(['jwt', 'active']);
    Route::post('/enrollments/self/enroll', [EnrollmentController::class, 'selfEnroll'])
        ->middleware(['throttle:enrollment', 'jwt', 'active', 'role:student']);
    Route::post('/enrollments/self/drop', [EnrollmentController::class, 'selfDrop'])
        ->middleware(['jwt', 'active', 'role:student']);
    Route::post('/enrollments/self/complete', [EnrollmentController::class, 'selfComplete'])
        ->middleware(['jwt', 'active', 'role:student']);
    // Identidade por FK (ADR 10): o recurso é endereçado pelo userId do aluno.
    Route::put('/enrollments/{userId}', [EnrollmentController::class, 'upsertEnrollment'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/admissions', [EnrollmentController::class, 'listAdmissions'])
        ->middleware(['jwt', 'active']);
    Route::post('/admissions', [EnrollmentController::class, 'createAdmission'])
        ->middleware(['throttle:enrollment', 'jwt', 'active', 'role:student,admin']);
    Route::put('/admissions/{id}', [EnrollmentController::class, 'updateAdmissionStatus'])
        ->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Etapa 3 — Certificados (flag certificados). Verificação pública; emissão/listagem
// autenticadas; exclusão só admin. Espelha src/server/routes/certificateRoutes.ts.
Route::middleware('feature:certificados')->group(function (): void {
    Route::get('/certificates/verify', [CertificateController::class, 'verify'])
        ->middleware('throttle:cert-lookup');
    Route::get('/certificates', [CertificateController::class, 'index'])
        ->middleware(['jwt', 'active']);
    Route::get('/certificates/{id}/pdf', [CertificateController::class, 'pdf'])
        ->middleware(['jwt', 'active']);
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->middleware(['jwt', 'active']);
    Route::delete('/certificates/{id}', [CertificateController::class, 'destroy'])
        ->middleware(['jwt', 'active', 'role:admin']);
});

// Etapa 4 — Uploads (flag uploadArquivos). Espelha src/server/upload.ts: upload com
// validação de magic bytes; download privado autorizado (dono/instrutor/admin).
// Arquivos PÚBLICOS continuam servidos pela rota estática /uploads do Node (origem única).
Route::middleware(['feature:uploadArquivos', 'jwt', 'active'])->group(function (): void {
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:upload');
    Route::get('/files/{id}', [UploadController::class, 'download']);
});

// ---------------- Etapa 5 — módulos restantes ----------------

// Quizzes e submissões (flag quizSimples).
Route::middleware('feature:quizSimples')->group(function (): void {
    // Exige autenticação: o gabarito (correctOptionIndex) não pode ser raspado
    // anonimamente. Alunos matriculados ainda recebem o gabarito para o fluxo de
    // feedback imediato do quiz — a nota é validada no servidor (submitQuiz).
    Route::get('/quizzes', [LearningController::class, 'listQuizzes'])->middleware(['jwt', 'active']);
    Route::post('/quizzes', [LearningController::class, 'createQuiz'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/quizzes/{id}', [LearningController::class, 'deleteQuiz'])->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/quiz-submissions', [LearningController::class, 'listQuizSubmissions'])->middleware(['jwt', 'active']);
    Route::post('/quiz-submissions', [LearningController::class, 'submitQuiz'])->middleware(['jwt', 'active', 'role:student']);
});

// Fórum (flag forum).
Route::middleware(['feature:forum', 'jwt', 'active'])->group(function (): void {
    Route::get('/forum', [LearningController::class, 'listForumMessages']);
    Route::post('/forum', [LearningController::class, 'createForumMessage']);
    Route::put('/forum/{id}/like', [LearningController::class, 'toggleForumLike']);
    Route::delete('/forum/{id}', [LearningController::class, 'deleteForumMessage']);
});

// Exercícios práticos e entregas (flag atividadesPraticasAvancadas).
Route::middleware('feature:atividadesPraticasAvancadas')->group(function (): void {
    Route::get('/exercises', [LearningController::class, 'listExercises'])->middleware(['jwt', 'active']);
    Route::post('/exercises', [LearningController::class, 'createExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::put('/exercises/{id}', [LearningController::class, 'updateExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);
    Route::delete('/exercises/{id}', [LearningController::class, 'deleteExercise'])->middleware(['jwt', 'active', 'role:instructor,admin']);

    Route::get('/exercise-submissions', [LearningController::class, 'listExerciseSubmissions'])->middleware(['jwt', 'active']);
    Route::post('/exercise-submissions', [LearningController::class, 'submitExercise'])->middleware(['jwt', 'active', 'role:student']);
    Route::put('/exercise-submissions/{id}/grade', [LearningController::class, 'gradeSubmission'])->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Chat de aula ao vivo (flag liveClassroom).
Route::middleware(['feature:liveClassroom', 'jwt', 'active'])->group(function (): void {
    Route::get('/chat', [MessagingController::class, 'listChatMessages']);
    Route::post('/chat', [MessagingController::class, 'createChatMessage']);
});

// Mensagens diretas (flag mensagensDiretas).
Route::middleware(['feature:mensagensDiretas', 'jwt', 'active'])->group(function (): void {
    Route::get('/dms', [MessagingController::class, 'listDirectMessages']);
    Route::post('/dms', [MessagingController::class, 'createDirectMessage']);
});

// Solicitações acadêmicas (flag solicitacoesAcademicas).
Route::middleware('feature:solicitacoesAcademicas')->group(function (): void {
    Route::get('/academic-requests', [RequestController::class, 'index'])->middleware(['jwt', 'active']);
    Route::post('/academic-requests', [RequestController::class, 'store'])->middleware(['throttle:justification', 'jwt', 'active', 'role:student,admin']);
    Route::put('/academic-requests/{id}', [RequestController::class, 'updateStatus'])->middleware(['jwt', 'active', 'role:instructor,admin']);
});

// Configurações do sistema (sem feature flag). GET público; PUT só admin.
Route::get('/system-settings', [SettingsController::class, 'show']);
Route::put('/system-settings', [SettingsController::class, 'update'])->middleware(['jwt', 'active', 'role:admin']);

// Auditoria (SecurityLog) — só leitura/limpeza por admin; nunca há POST (gravação é server-side).
Route::prefix('security-logs')->middleware(['jwt', 'active', 'role:admin'])->group(function (): void {
    Route::get('/', [AuditController::class, 'listSecurityLogs']);
    Route::delete('/', [AuditController::class, 'clearSecurityLogs']);
});

// Telemetria (ClientEvent) — POST aceita anônimo (identidade vem do token se houver); GET só admin.
Route::post('/telemetry', [AuditController::class, 'recordClientEvent'])->middleware('jwt.optional');
Route::get('/telemetry', [AuditController::class, 'listClientEvents'])->middleware(['jwt', 'active', 'role:admin']);

// Exportação de Dados Gerenciais (flag dadosGerenciais) — só admin, com rate limit e auditoria.
Route::middleware('feature:dadosGerenciais')->group(function (): void {
    Route::get('/export/{dataset}', [ExportController::class, 'show'])
        ->middleware(['throttle:export', 'jwt', 'active', 'role:admin']);
});
