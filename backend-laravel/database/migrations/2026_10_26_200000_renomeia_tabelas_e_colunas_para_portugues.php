<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tabelas e colunas em português, em snake_case (ADR 024).
 *
 * Só renomeia: nenhuma linha, coluna ou índice é apagado, e os tipos não mudam
 * (ADR 012 — nada é apagado). As chaves estrangeiras são recriadas com os mesmos
 * ON UPDATE/ON DELETE, só com nome novo. Os valores dos ENUM e o JSON guardado
 * dentro das colunas continuam como estão: são dado, não nomenclatura.
 *
 * `migrations` (tabela do framework) e `_prisma_migrations` (histórico do Node)
 * ficam de fora. Reversível: `down()` aplica o mapa ao contrário.
 *
 * Escrita com chamadas literais da API `Schema`, e não em laço sobre um mapa: o
 * Larastan deduz as propriedades dos models LENDO as migrations, e só entende
 * `Schema::rename()` e `renameColumn()` literais. O mapa completo está no ADR 024.
 */
return new class extends Migration
{
    /**
     * [tabela antiga, coluna antiga, tabela ref. antiga, coluna ref. antiga, nome antigo da FK,
     *  tabela nova, coluna nova, tabela ref. nova, coluna ref. nova, nome novo da FK]
     *
     * @var list<array{string, string, string, string, string, string, string, string, string, string}>
     */
    private const FKS = [
        ['AcademicRequest', 'userId', 'User', 'id', 'AcademicRequest_userId_fkey', 'solicitacao_academica', 'usuario_id', 'usuario', 'id', 'fk_solicitacao_academica_usuario_id'],
        ['AdmissionRequest', 'userId', 'User', 'id', 'AdmissionRequest_userId_fkey', 'solicitacao_ingresso', 'usuario_id', 'usuario', 'id', 'fk_solicitacao_ingresso_usuario_id'],
        ['Certificate', 'enrollmentId', 'StudentEnrollment', 'id', 'Certificate_enrollmentId_fkey', 'certificado', 'matricula_id', 'matricula', 'id', 'fk_certificado_matricula_id'],
        ['Certificate', 'userId', 'User', 'id', 'Certificate_userId_fkey', 'certificado', 'usuario_id', 'usuario', 'id', 'fk_certificado_usuario_id'],
        ['ChatMessage', 'senderUserId', 'User', 'id', 'ChatMessage_senderUserId_fkey', 'mensagem_chat', 'remetente_usuario_id', 'usuario', 'id', 'fk_mensagem_chat_remetente_usuario_id'],
        ['Course', 'instructorId', 'User', 'id', 'Course_instructorId_fkey', 'curso', 'instrutor_id', 'usuario', 'id', 'fk_curso_instrutor_id'],
        ['DirectMessage', 'senderUserId', 'User', 'id', 'DirectMessage_senderUserId_fkey', 'mensagem_direta', 'remetente_usuario_id', 'usuario', 'id', 'fk_mensagem_direta_remetente_usuario_id'],
        ['DirectMessage', 'studentUserId', 'User', 'id', 'DirectMessage_studentUserId_fkey', 'mensagem_direta', 'aluno_usuario_id', 'usuario', 'id', 'fk_mensagem_direta_aluno_usuario_id'],
        ['DocumentTemplate', 'updatedByUserId', 'User', 'id', 'documenttemplate_updatedby_fkey', 'modelo_documento', 'atualizado_por_usuario_id', 'usuario', 'id', 'fk_modelo_documento_atualizado_por_usuario_id'],
        ['ExerciseSubmission', 'exerciseId', 'PracticalExercise', 'id', 'ExerciseSubmission_exerciseId_fkey', 'entrega_exercicio', 'exercicio_id', 'exercicio_pratico', 'id', 'fk_entrega_exercicio_exercicio_id'],
        ['ExerciseSubmission', 'userId', 'User', 'id', 'ExerciseSubmission_userId_fkey', 'entrega_exercicio', 'usuario_id', 'usuario', 'id', 'fk_entrega_exercicio_usuario_id'],
        ['ForumMessage', 'senderUserId', 'User', 'id', 'ForumMessage_senderUserId_fkey', 'mensagem_forum', 'remetente_usuario_id', 'usuario', 'id', 'fk_mensagem_forum_remetente_usuario_id'],
        ['Lesson', 'courseId', 'Course', 'id', 'Lesson_courseId_fkey', 'aula', 'curso_id', 'curso', 'id', 'fk_aula_curso_id'],
        ['LessonDocument', 'lessonId', 'Lesson', 'id', 'LessonDocument_lessonId_fkey', 'documento_aula', 'aula_id', 'aula', 'id', 'fk_documento_aula_aula_id'],
        ['LiveSession', 'courseId', 'Course', 'id', 'LiveSession_courseId_fkey', 'sessao_ao_vivo', 'curso_id', 'curso', 'id', 'fk_sessao_ao_vivo_curso_id'],
        ['QuizQuestion', 'quizId', 'Quiz', 'id', 'QuizQuestion_quizId_fkey', 'questao_avaliacao', 'avaliacao_id', 'avaliacao', 'id', 'fk_questao_avaliacao_avaliacao_id'],
        ['QuizSubmission', 'userId', 'User', 'id', 'QuizSubmission_userId_fkey', 'tentativa_avaliacao', 'usuario_id', 'usuario', 'id', 'fk_tentativa_avaliacao_usuario_id'],
        ['SitePageContent', 'updatedByUserId', 'User', 'id', 'sitepagecontent_updatedby_fkey', 'conteudo_pagina_site', 'atualizado_por_usuario_id', 'usuario', 'id', 'fk_conteudo_pagina_site_atualizado_por_usuario_id'],
        ['StoredFile', 'ownerUserId', 'User', 'id', 'StoredFile_ownerUserId_fkey', 'arquivo_armazenado', 'dono_usuario_id', 'usuario', 'id', 'fk_arquivo_armazenado_dono_usuario_id'],
        ['StudentEnrollment', 'userId', 'User', 'id', 'StudentEnrollment_userId_fkey', 'matricula', 'usuario_id', 'usuario', 'id', 'fk_matricula_usuario_id'],
        ['StudentProgress', 'enrollmentId', 'StudentEnrollment', 'id', 'StudentProgress_enrollmentId_fkey', 'progresso_aluno', 'matricula_id', 'matricula', 'id', 'fk_progresso_aluno_matricula_id'],
        ['StudentProgress', 'userId', 'User', 'id', 'StudentProgress_userId_fkey', 'progresso_aluno', 'usuario_id', 'usuario', 'id', 'fk_progresso_aluno_usuario_id'],
    ];

    /**
     * ON UPDATE / ON DELETE de cada chave, pelo nome antigo.
     *
     * @var array<string, array{string, string}>
     */
    private const REGRAS = [
        'AcademicRequest_userId_fkey' => ['CASCADE', 'RESTRICT'],
        'AdmissionRequest_userId_fkey' => ['CASCADE', 'RESTRICT'],
        'Certificate_enrollmentId_fkey' => ['CASCADE', 'SET NULL'],
        'Certificate_userId_fkey' => ['CASCADE', 'SET NULL'],
        'ChatMessage_senderUserId_fkey' => ['CASCADE', 'SET NULL'],
        'Course_instructorId_fkey' => ['CASCADE', 'SET NULL'],
        'DirectMessage_senderUserId_fkey' => ['CASCADE', 'SET NULL'],
        'DirectMessage_studentUserId_fkey' => ['CASCADE', 'RESTRICT'],
        'documenttemplate_updatedby_fkey' => ['CASCADE', 'SET NULL'],
        'ExerciseSubmission_exerciseId_fkey' => ['CASCADE', 'RESTRICT'],
        'ExerciseSubmission_userId_fkey' => ['CASCADE', 'RESTRICT'],
        'ForumMessage_senderUserId_fkey' => ['CASCADE', 'SET NULL'],
        'Lesson_courseId_fkey' => ['CASCADE', 'RESTRICT'],
        'LessonDocument_lessonId_fkey' => ['CASCADE', 'RESTRICT'],
        'LiveSession_courseId_fkey' => ['CASCADE', 'RESTRICT'],
        'QuizQuestion_quizId_fkey' => ['CASCADE', 'RESTRICT'],
        'QuizSubmission_userId_fkey' => ['CASCADE', 'RESTRICT'],
        'sitepagecontent_updatedby_fkey' => ['CASCADE', 'SET NULL'],
        'StoredFile_ownerUserId_fkey' => ['CASCADE', 'SET NULL'],
        'StudentEnrollment_userId_fkey' => ['CASCADE', 'RESTRICT'],
        'StudentProgress_enrollmentId_fkey' => ['CASCADE', 'SET NULL'],
        'StudentProgress_userId_fkey' => ['CASCADE', 'RESTRICT'],
    ];

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::FKS as [$tabela, , , , $antigo]) {
            DB::statement("ALTER TABLE `{$tabela}` DROP FOREIGN KEY `{$antigo}`");
        }

        Schema::rename('AcademicRequest', 'solicitacao_academica');
        Schema::rename('AdmissionRequest', 'solicitacao_ingresso');
        Schema::rename('Certificate', 'certificado');
        Schema::rename('ChatMessage', 'mensagem_chat');
        Schema::rename('ClientEvent', 'evento_cliente');
        Schema::rename('Course', 'curso');
        Schema::rename('CourseSlugHistory', 'historico_slug_curso');
        Schema::rename('DirectMessage', 'mensagem_direta');
        Schema::rename('DocumentTemplate', 'modelo_documento');
        Schema::rename('ExerciseSubmission', 'entrega_exercicio');
        Schema::rename('ForumMessage', 'mensagem_forum');
        Schema::rename('Lesson', 'aula');
        Schema::rename('LessonDocument', 'documento_aula');
        Schema::rename('LibraryItem', 'item_biblioteca');
        Schema::rename('LiveSession', 'sessao_ao_vivo');
        Schema::rename('PracticalExercise', 'exercicio_pratico');
        Schema::rename('Quiz', 'avaliacao');
        Schema::rename('QuizQuestion', 'questao_avaliacao');
        Schema::rename('QuizSubmission', 'tentativa_avaliacao');
        Schema::rename('SecurityLog', 'log_seguranca');
        Schema::rename('SessaoEncerrada', 'sessao_encerrada');
        Schema::rename('SitePageContent', 'conteudo_pagina_site');
        Schema::rename('StoredFile', 'arquivo_armazenado');
        Schema::rename('StudentEnrollment', 'matricula');
        Schema::rename('StudentProgress', 'progresso_aluno');
        Schema::rename('SystemSettings', 'configuracao_sistema');
        Schema::rename('User', 'usuario');
        Schema::rename('VersaoDeConteudo', 'versao_de_conteudo');
        Schema::rename('WebinarEvent', 'evento_webinar');

        Schema::table('solicitacao_academica', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('type', 'tipo');
            $table->renameColumn('description', 'descricao');
            $table->renameColumn('submittedAt', 'enviado_em_texto');
            $table->renameColumn('courseTitle', 'titulo_curso');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('academicrequest_inativadoem_idx', 'idx_solicitacao_academica_inativado_em');
            $table->renameIndex('AcademicRequest_status_idx', 'idx_solicitacao_academica_status');
            $table->renameIndex('AcademicRequest_studentName_idx', 'idx_solicitacao_academica_nome_aluno');
            $table->renameIndex('AcademicRequest_userId_idx', 'idx_solicitacao_academica_usuario_id');
        });
        Schema::table('solicitacao_ingresso', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('submittedAt', 'enviado_em_texto');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('AdmissionRequest_courseId_idx', 'idx_solicitacao_ingresso_curso_id');
            $table->renameIndex('admissionrequest_inativadoem_idx', 'idx_solicitacao_ingresso_inativado_em');
            $table->renameIndex('AdmissionRequest_studentName_idx', 'idx_solicitacao_ingresso_nome_aluno');
            $table->renameIndex('admissionrequest_userid_courseid_status_idx', 'idx_solicitacao_ingresso_usuario_id_curso_id_status');
            $table->renameIndex('AdmissionRequest_userId_idx', 'idx_solicitacao_ingresso_usuario_id');
        });
        Schema::table('certificado', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('courseTitle', 'titulo_curso');
            $table->renameColumn('attendancePercent', 'percentual_frequencia');
            $table->renameColumn('verificationHash', 'hash_verificacao');
            $table->renameColumn('enrollmentId', 'matricula_id');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('issueDate', 'data_emissao');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('Certificate_courseId_idx', 'idx_certificado_curso_id');
            $table->renameIndex('Certificate_enrollmentId_idx', 'idx_certificado_matricula_id');
            $table->renameIndex('certificate_inativadoem_idx', 'idx_certificado_inativado_em');
            $table->renameIndex('Certificate_studentName_idx', 'idx_certificado_nome_aluno');
            $table->renameIndex('certificate_userid_courseid_key', 'uq_certificado_usuario_id_curso_id');
            $table->renameIndex('Certificate_userId_idx', 'idx_certificado_usuario_id');
        });
        Schema::table('mensagem_chat', function (Blueprint $table): void {
            $table->renameColumn('sessionId', 'sessao_id');
            $table->renameColumn('senderName', 'nome_remetente');
            $table->renameColumn('senderRole', 'papel_remetente');
            $table->renameColumn('text', 'texto');
            $table->renameColumn('timestamp', 'registrado_em_texto');
            $table->renameColumn('enviadaEm', 'enviada_em');
            $table->renameColumn('senderUserId', 'remetente_usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('chatmessage_enviadaem_idx', 'idx_mensagem_chat_enviada_em');
            $table->renameIndex('chatmessage_inativadoem_idx', 'idx_mensagem_chat_inativado_em');
            $table->renameIndex('ChatMessage_senderUserId_idx', 'idx_mensagem_chat_remetente_usuario_id');
            $table->renameIndex('ChatMessage_sessionId_idx', 'idx_mensagem_chat_sessao_id');
        });
        Schema::table('evento_cliente', function (Blueprint $table): void {
            $table->renameColumn('createdAt', 'criado_em');
            $table->renameColumn('user', 'usuario');
            $table->renameColumn('role', 'papel');
            $table->renameColumn('ipAddress', 'ip');
            $table->renameColumn('device', 'dispositivo');
            $table->renameColumn('action', 'acao');
            $table->renameColumn('details', 'detalhes');
            $table->renameIndex('ClientEvent_createdAt_idx', 'idx_evento_cliente_criado_em');
            $table->renameIndex('ClientEvent_user_idx', 'idx_evento_cliente_usuario');
        });
        Schema::table('curso', function (Blueprint $table): void {
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('description', 'descricao');
            $table->renameColumn('category', 'categoria');
            $table->renameColumn('thumbnail', 'miniatura');
            $table->renameColumn('instructorName', 'nome_instrutor');
            $table->renameColumn('coverImage', 'imagem_capa');
            $table->renameColumn('courseType', 'tipo_curso');
            $table->renameColumn('hasChat', 'tem_chat');
            $table->renameColumn('minAttendance', 'frequencia_minima');
            $table->renameColumn('contractExpirationDate', 'vencimento_contrato_texto');
            $table->renameColumn('vigenciaAte', 'vigencia_ate');
            $table->renameColumn('areaTematica', 'area_tematica');
            $table->renameColumn('cargaHoraria', 'carga_horaria');
            $table->renameColumn('emiteCertificado', 'emite_certificado');
            $table->renameColumn('statusCurso', 'status_curso');
            $table->renameColumn('instructorId', 'instrutor_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('course_inativadoem_idx', 'idx_curso_inativado_em');
            $table->renameIndex('Course_instructorId_idx', 'idx_curso_instrutor_id');
            $table->renameIndex('Course_instructorName_idx', 'idx_curso_nome_instrutor');
            $table->renameIndex('course_slug_unique', 'uq_curso_slug');
            $table->renameIndex('course_status_idx', 'idx_curso_status');
        });
        Schema::table('historico_slug_curso', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('aposentadoEm', 'aposentado_em');
            $table->renameIndex('courseslughistory_courseid_idx', 'idx_historico_slug_curso_curso_id');
        });
        Schema::table('mensagem_direta', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('senderName', 'nome_remetente');
            $table->renameColumn('senderRole', 'papel_remetente');
            $table->renameColumn('text', 'texto');
            $table->renameColumn('timestamp', 'registrado_em_texto');
            $table->renameColumn('enviadaEm', 'enviada_em');
            $table->renameColumn('senderUserId', 'remetente_usuario_id');
            $table->renameColumn('studentUserId', 'aluno_usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('directmessage_enviadaem_idx', 'idx_mensagem_direta_enviada_em');
            $table->renameIndex('directmessage_inativadoem_idx', 'idx_mensagem_direta_inativado_em');
            $table->renameIndex('DirectMessage_senderUserId_idx', 'idx_mensagem_direta_remetente_usuario_id');
            $table->renameIndex('DirectMessage_studentName_idx', 'idx_mensagem_direta_nome_aluno');
            $table->renameIndex('DirectMessage_studentUserId_idx', 'idx_mensagem_direta_aluno_usuario_id');
        });
        Schema::table('modelo_documento', function (Blueprint $table): void {
            $table->renameColumn('type', 'tipo');
            $table->renameColumn('institutionName', 'nome_instituicao');
            $table->renameColumn('institutionLogoPath', 'caminho_logo_instituicao');
            $table->renameColumn('signatories', 'signatarios');
            $table->renameColumn('footerText', 'texto_rodape');
            $table->renameColumn('customHtml', 'html_personalizado');
            $table->renameColumn('updatedByUserId', 'atualizado_por_usuario_id');
            $table->renameColumn('updatedAt', 'atualizado_em');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('documenttemplate_inativadoem_idx', 'idx_modelo_documento_inativado_em');
            $table->renameIndex('documenttemplate_status_idx', 'idx_modelo_documento_status');
            $table->renameIndex('documenttemplate_updatedby_fkey', 'idx_modelo_documento_atualizado_por_usuario_id');
        });
        Schema::table('entrega_exercicio', function (Blueprint $table): void {
            $table->renameColumn('exerciseId', 'exercicio_id');
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('submissionText', 'texto_entrega');
            $table->renameColumn('fileUrl', 'url_arquivo');
            $table->renameColumn('fileName', 'nome_arquivo');
            $table->renameColumn('submittedAt', 'enviado_em_texto');
            $table->renameColumn('score', 'nota');
            $table->renameColumn('feedback', 'devolutiva');
            $table->renameColumn('gradedAt', 'corrigido_em_texto');
            $table->renameColumn('gradedBy', 'corrigido_por');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('ExerciseSubmission_exerciseId_idx', 'idx_entrega_exercicio_exercicio_id');
            $table->renameIndex('exercisesubmission_inativadoem_idx', 'idx_entrega_exercicio_inativado_em');
            $table->renameIndex('ExerciseSubmission_studentName_idx', 'idx_entrega_exercicio_nome_aluno');
            $table->renameIndex('ExerciseSubmission_userId_idx', 'idx_entrega_exercicio_usuario_id');
        });
        Schema::table('mensagem_forum', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('senderName', 'nome_remetente');
            $table->renameColumn('senderRole', 'papel_remetente');
            $table->renameColumn('text', 'texto');
            $table->renameColumn('timestamp', 'registrado_em_texto');
            $table->renameColumn('enviadaEm', 'enviada_em');
            $table->renameColumn('likes', 'curtidas');
            $table->renameColumn('likedBy', 'curtido_por');
            $table->renameColumn('senderUserId', 'remetente_usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('ForumMessage_courseId_idx', 'idx_mensagem_forum_curso_id');
            $table->renameIndex('forummessage_enviadaem_idx', 'idx_mensagem_forum_enviada_em');
            $table->renameIndex('forummessage_inativadoem_idx', 'idx_mensagem_forum_inativado_em');
            $table->renameIndex('ForumMessage_senderUserId_idx', 'idx_mensagem_forum_remetente_usuario_id');
        });
        Schema::table('aula', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('duration', 'duracao');
            $table->renameColumn('videoUrl', 'url_video');
            $table->renameColumn('content', 'conteudo');
            $table->renameColumn('lesson_order', 'ordem');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('Lesson_courseId_idx', 'idx_aula_curso_id');
            $table->renameIndex('lesson_inativadoem_idx', 'idx_aula_inativado_em');
            $table->renameIndex('lesson_status_idx', 'idx_aula_status');
        });
        Schema::table('documento_aula', function (Blueprint $table): void {
            $table->renameColumn('lessonId', 'aula_id');
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('type', 'tipo');
            $table->renameColumn('size', 'tamanho');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('lessondocument_inativadoem_idx', 'idx_documento_aula_inativado_em');
            $table->renameIndex('LessonDocument_lessonId_idx', 'idx_documento_aula_aula_id');
            $table->renameIndex('lessondocument_status_idx', 'idx_documento_aula_status');
        });
        Schema::table('item_biblioteca', function (Blueprint $table): void {
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('type', 'tipo');
            $table->renameColumn('category', 'categoria');
            $table->renameColumn('description', 'descricao');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('libraryitem_inativadoem_idx', 'idx_item_biblioteca_inativado_em');
            $table->renameIndex('libraryitem_status_idx', 'idx_item_biblioteca_status');
        });
        Schema::table('sessao_ao_vivo', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('scheduledAt', 'agendado_para_texto');
            $table->renameColumn('durationMinutes', 'duracao_minutos');
            $table->renameColumn('meetingLink', 'link_reuniao');
            $table->renameColumn('isLive', 'ao_vivo');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('LiveSession_courseId_idx', 'idx_sessao_ao_vivo_curso_id');
            $table->renameIndex('livesession_inativadoem_idx', 'idx_sessao_ao_vivo_inativado_em');
            $table->renameIndex('livesession_status_idx', 'idx_sessao_ao_vivo_status');
        });
        Schema::table('exercicio_pratico', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('description', 'descricao');
            $table->renameColumn('instructions', 'instrucoes');
            $table->renameColumn('maxPoints', 'pontuacao_maxima');
            $table->renameColumn('dueDate', 'prazo_texto');
            $table->renameColumn('prazoEm', 'prazo_em');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('PracticalExercise_courseId_idx', 'idx_exercicio_pratico_curso_id');
            $table->renameIndex('practicalexercise_inativadoem_idx', 'idx_exercicio_pratico_inativado_em');
            $table->renameIndex('practicalexercise_status_idx', 'idx_exercicio_pratico_status');
        });
        Schema::table('avaliacao', function (Blueprint $table): void {
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('Quiz_courseId_idx', 'idx_avaliacao_curso_id');
            $table->renameIndex('quiz_inativadoem_idx', 'idx_avaliacao_inativado_em');
            $table->renameIndex('quiz_status_idx', 'idx_avaliacao_status');
        });
        Schema::table('questao_avaliacao', function (Blueprint $table): void {
            $table->renameColumn('quizId', 'avaliacao_id');
            $table->renameColumn('questionText', 'texto_questao');
            $table->renameColumn('options', 'opcoes');
            $table->renameColumn('correctOptionIndex', 'indice_opcao_correta');
            $table->renameColumn('explanation', 'explicacao');
            $table->renameColumn('reviewMessage', 'mensagem_revisao');
            $table->renameColumn('recommendedModule', 'modulo_recomendado');
            $table->renameColumn('allowRetry', 'permite_nova_tentativa');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('quizquestion_inativadoem_idx', 'idx_questao_avaliacao_inativado_em');
            $table->renameIndex('QuizQuestion_quizId_idx', 'idx_questao_avaliacao_avaliacao_id');
        });
        Schema::table('tentativa_avaliacao', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('quizId', 'avaliacao_id');
            $table->renameColumn('scorePercent', 'percentual_nota');
            $table->renameColumn('passed', 'aprovado');
            $table->renameColumn('submittedAt', 'enviado_em_texto');
            $table->renameColumn('enviadoEm', 'enviado_em');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('QuizSubmission_courseId_idx', 'idx_tentativa_avaliacao_curso_id');
            $table->renameIndex('quizsubmission_enviadoem_idx', 'idx_tentativa_avaliacao_enviado_em');
            $table->renameIndex('quizsubmission_inativadoem_idx', 'idx_tentativa_avaliacao_inativado_em');
            $table->renameIndex('QuizSubmission_quizId_idx', 'idx_tentativa_avaliacao_avaliacao_id');
            $table->renameIndex('QuizSubmission_studentName_idx', 'idx_tentativa_avaliacao_nome_aluno');
            $table->renameIndex('QuizSubmission_userId_idx', 'idx_tentativa_avaliacao_usuario_id');
        });
        Schema::table('log_seguranca', function (Blueprint $table): void {
            $table->renameColumn('timestamp', 'registrado_em_texto');
            $table->renameColumn('ocorridoEm', 'ocorrido_em');
            $table->renameColumn('user', 'usuario');
            $table->renameColumn('role', 'papel');
            $table->renameColumn('ipAddress', 'ip');
            $table->renameColumn('device', 'dispositivo');
            $table->renameColumn('action', 'acao');
            $table->renameColumn('details', 'detalhes');
            $table->renameIndex('SecurityLog_action_idx', 'idx_log_seguranca_acao');
            $table->renameIndex('securitylog_ocorridoem_idx', 'idx_log_seguranca_ocorrido_em');
            $table->renameIndex('SecurityLog_timestamp_idx', 'idx_log_seguranca_registrado_em_texto');
            $table->renameIndex('SecurityLog_user_idx', 'idx_log_seguranca_usuario');
        });
        Schema::table('sessao_encerrada', function (Blueprint $table): void {
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('encerradaEm', 'encerrada_em');
            $table->renameIndex('sessaoencerrada_user_idx', 'idx_sessao_encerrada_usuario_id');
        });
        Schema::table('conteudo_pagina_site', function (Blueprint $table): void {
            $table->renameColumn('pageKey', 'chave_pagina');
            $table->renameColumn('content', 'conteudo');
            $table->renameColumn('updatedByUserId', 'atualizado_por_usuario_id');
            $table->renameColumn('updatedAt', 'atualizado_em');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('sitepagecontent_inativadoem_idx', 'idx_conteudo_pagina_site_inativado_em');
            $table->renameIndex('sitepagecontent_status_idx', 'idx_conteudo_pagina_site_status');
            $table->renameIndex('sitepagecontent_updatedby_fkey', 'idx_conteudo_pagina_site_atualizado_por_usuario_id');
        });
        Schema::table('arquivo_armazenado', function (Blueprint $table): void {
            $table->renameColumn('originalName', 'nome_original');
            $table->renameColumn('visibility', 'visibilidade');
            $table->renameColumn('ownerUserId', 'dono_usuario_id');
            $table->renameColumn('createdAt', 'criado_em');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('storedfile_inativadoem_idx', 'idx_arquivo_armazenado_inativado_em');
            $table->renameIndex('StoredFile_ownerUserId_idx', 'idx_arquivo_armazenado_dono_usuario_id');
        });
        Schema::table('matricula', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('enrolledCourseId', 'curso_matriculado_id');
            $table->renameColumn('enrolledAt', 'matriculado_em_texto');
            $table->renameColumn('completedCourseIds', 'cursos_concluidos_ids');
            $table->renameColumn('dropOutPenaltyUntil', 'penalidade_desistencia_ate');
            $table->renameColumn('canMultiEnroll', 'pode_multimatricula');
            $table->renameColumn('extraCourseIds', 'cursos_extras_ids');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('StudentEnrollment_id_key', 'uq_matricula_id');
            $table->renameIndex('studentenrollment_inativadoem_idx', 'idx_matricula_inativado_em');
            $table->renameIndex('studentenrollment_studentname_idx', 'idx_matricula_nome_aluno');
        });
        Schema::table('progresso_aluno', function (Blueprint $table): void {
            $table->renameColumn('studentName', 'nome_aluno');
            $table->renameColumn('courseId', 'curso_id');
            $table->renameColumn('completedLessons', 'aulas_concluidas');
            $table->renameColumn('attendedLiveSessions', 'sessoes_ao_vivo_assistidas');
            $table->renameColumn('enrollmentId', 'matricula_id');
            $table->renameColumn('userId', 'usuario_id');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('StudentProgress_courseId_idx', 'idx_progresso_aluno_curso_id');
            $table->renameIndex('StudentProgress_enrollmentId_idx', 'idx_progresso_aluno_matricula_id');
            $table->renameIndex('studentprogress_inativadoem_idx', 'idx_progresso_aluno_inativado_em');
            $table->renameIndex('studentprogress_userid_courseid_key', 'uq_progresso_aluno_usuario_id_curso_id');
            $table->renameIndex('StudentProgress_userId_idx', 'idx_progresso_aluno_usuario_id');
        });
        Schema::table('configuracao_sistema', function (Blueprint $table): void {
            $table->renameColumn('data', 'dados');
        });
        Schema::table('usuario', function (Blueprint $table): void {
            $table->renameColumn('name', 'nome');
            $table->renameColumn('passwordHash', 'senha_hash');
            $table->renameColumn('role', 'papel');
            $table->renameColumn('nomeSocial', 'nome_social');
            $table->renameColumn('createdAt', 'criado_em');
            $table->renameColumn('updatedAt', 'atualizado_em');
            $table->renameColumn('areaInteresse', 'area_interesse');
            $table->renameColumn('dataCadastro', 'data_cadastro_texto');
            $table->renameColumn('failedLoginAttempts', 'tentativas_login_falhas');
            $table->renameColumn('lockedUntil', 'bloqueado_ate');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameColumn('versaoDaSessao', 'versao_da_sessao');
            $table->renameIndex('user_cpf_key', 'uq_usuario_cpf');
            $table->renameIndex('User_email_key', 'uq_usuario_email');
            $table->renameIndex('user_inativadoem_idx', 'idx_usuario_inativado_em');
            $table->renameIndex('User_role_idx', 'idx_usuario_papel');
            $table->renameIndex('User_role_status_idx', 'idx_usuario_papel_status');
            $table->renameIndex('User_status_idx', 'idx_usuario_status');
        });
        Schema::table('versao_de_conteudo', function (Blueprint $table): void {
            $table->renameColumn('criadoPorUserId', 'criado_por_usuario_id');
            $table->renameColumn('criadoEm', 'criado_em');
            $table->renameIndex('versaodeconteudo_item_idx', 'idx_versao_de_conteudo_tipo_referencia_criado_em');
        });
        Schema::table('evento_webinar', function (Blueprint $table): void {
            $table->renameColumn('title', 'titulo');
            $table->renameColumn('date', 'data_texto');
            $table->renameColumn('dataEvento', 'data_evento');
            $table->renameColumn('time', 'horario');
            $table->renameColumn('description', 'descricao');
            $table->renameColumn('image', 'imagem');
            $table->renameColumn('inativadoEm', 'inativado_em');
            $table->renameColumn('inativadoPor', 'inativado_por');
            $table->renameColumn('motivoInativacao', 'motivo_inativacao');
            $table->renameIndex('webinarevent_inativadoem_idx', 'idx_evento_webinar_inativado_em');
            $table->renameIndex('webinarevent_status_idx', 'idx_evento_webinar_status');
        });

        foreach (self::FKS as [, , , , $antigo, $tabela, $coluna, $refTabela, $refColuna, $novo]) {
            [$upd, $del] = self::REGRAS[$antigo];
            DB::statement(
                "ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$novo}` FOREIGN KEY (`{$coluna}`) "
                ."REFERENCES `{$refTabela}` (`{$refColuna}`) ON UPDATE {$upd} ON DELETE {$del}"
            );
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::FKS as [, , , , , $tabela, , , , $novo]) {
            DB::statement("ALTER TABLE `{$tabela}` DROP FOREIGN KEY `{$novo}`");
        }

        Schema::table('solicitacao_academica', function (Blueprint $table): void {
            $table->renameIndex('idx_solicitacao_academica_inativado_em', 'academicrequest_inativadoem_idx');
            $table->renameIndex('idx_solicitacao_academica_status', 'AcademicRequest_status_idx');
            $table->renameIndex('idx_solicitacao_academica_nome_aluno', 'AcademicRequest_studentName_idx');
            $table->renameIndex('idx_solicitacao_academica_usuario_id', 'AcademicRequest_userId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('tipo', 'type');
            $table->renameColumn('descricao', 'description');
            $table->renameColumn('enviado_em_texto', 'submittedAt');
            $table->renameColumn('titulo_curso', 'courseTitle');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('solicitacao_ingresso', function (Blueprint $table): void {
            $table->renameIndex('idx_solicitacao_ingresso_curso_id', 'AdmissionRequest_courseId_idx');
            $table->renameIndex('idx_solicitacao_ingresso_inativado_em', 'admissionrequest_inativadoem_idx');
            $table->renameIndex('idx_solicitacao_ingresso_nome_aluno', 'AdmissionRequest_studentName_idx');
            $table->renameIndex('idx_solicitacao_ingresso_usuario_id_curso_id_status', 'admissionrequest_userid_courseid_status_idx');
            $table->renameIndex('idx_solicitacao_ingresso_usuario_id', 'AdmissionRequest_userId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('enviado_em_texto', 'submittedAt');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('certificado', function (Blueprint $table): void {
            $table->renameIndex('idx_certificado_curso_id', 'Certificate_courseId_idx');
            $table->renameIndex('idx_certificado_matricula_id', 'Certificate_enrollmentId_idx');
            $table->renameIndex('idx_certificado_inativado_em', 'certificate_inativadoem_idx');
            $table->renameIndex('idx_certificado_nome_aluno', 'Certificate_studentName_idx');
            $table->renameIndex('uq_certificado_usuario_id_curso_id', 'certificate_userid_courseid_key');
            $table->renameIndex('idx_certificado_usuario_id', 'Certificate_userId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('titulo_curso', 'courseTitle');
            $table->renameColumn('percentual_frequencia', 'attendancePercent');
            $table->renameColumn('hash_verificacao', 'verificationHash');
            $table->renameColumn('matricula_id', 'enrollmentId');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('data_emissao', 'issueDate');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('mensagem_chat', function (Blueprint $table): void {
            $table->renameIndex('idx_mensagem_chat_enviada_em', 'chatmessage_enviadaem_idx');
            $table->renameIndex('idx_mensagem_chat_inativado_em', 'chatmessage_inativadoem_idx');
            $table->renameIndex('idx_mensagem_chat_remetente_usuario_id', 'ChatMessage_senderUserId_idx');
            $table->renameIndex('idx_mensagem_chat_sessao_id', 'ChatMessage_sessionId_idx');
            $table->renameColumn('sessao_id', 'sessionId');
            $table->renameColumn('nome_remetente', 'senderName');
            $table->renameColumn('papel_remetente', 'senderRole');
            $table->renameColumn('texto', 'text');
            $table->renameColumn('registrado_em_texto', 'timestamp');
            $table->renameColumn('enviada_em', 'enviadaEm');
            $table->renameColumn('remetente_usuario_id', 'senderUserId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('evento_cliente', function (Blueprint $table): void {
            $table->renameIndex('idx_evento_cliente_criado_em', 'ClientEvent_createdAt_idx');
            $table->renameIndex('idx_evento_cliente_usuario', 'ClientEvent_user_idx');
            $table->renameColumn('criado_em', 'createdAt');
            $table->renameColumn('usuario', 'user');
            $table->renameColumn('papel', 'role');
            $table->renameColumn('ip', 'ipAddress');
            $table->renameColumn('dispositivo', 'device');
            $table->renameColumn('acao', 'action');
            $table->renameColumn('detalhes', 'details');
        });
        Schema::table('curso', function (Blueprint $table): void {
            $table->renameIndex('idx_curso_inativado_em', 'course_inativadoem_idx');
            $table->renameIndex('idx_curso_instrutor_id', 'Course_instructorId_idx');
            $table->renameIndex('idx_curso_nome_instrutor', 'Course_instructorName_idx');
            $table->renameIndex('uq_curso_slug', 'course_slug_unique');
            $table->renameIndex('idx_curso_status', 'course_status_idx');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('descricao', 'description');
            $table->renameColumn('categoria', 'category');
            $table->renameColumn('miniatura', 'thumbnail');
            $table->renameColumn('nome_instrutor', 'instructorName');
            $table->renameColumn('imagem_capa', 'coverImage');
            $table->renameColumn('tipo_curso', 'courseType');
            $table->renameColumn('tem_chat', 'hasChat');
            $table->renameColumn('frequencia_minima', 'minAttendance');
            $table->renameColumn('vencimento_contrato_texto', 'contractExpirationDate');
            $table->renameColumn('vigencia_ate', 'vigenciaAte');
            $table->renameColumn('area_tematica', 'areaTematica');
            $table->renameColumn('carga_horaria', 'cargaHoraria');
            $table->renameColumn('emite_certificado', 'emiteCertificado');
            $table->renameColumn('status_curso', 'statusCurso');
            $table->renameColumn('instrutor_id', 'instructorId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('historico_slug_curso', function (Blueprint $table): void {
            $table->renameIndex('idx_historico_slug_curso_curso_id', 'courseslughistory_courseid_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('aposentado_em', 'aposentadoEm');
        });
        Schema::table('mensagem_direta', function (Blueprint $table): void {
            $table->renameIndex('idx_mensagem_direta_enviada_em', 'directmessage_enviadaem_idx');
            $table->renameIndex('idx_mensagem_direta_inativado_em', 'directmessage_inativadoem_idx');
            $table->renameIndex('idx_mensagem_direta_remetente_usuario_id', 'DirectMessage_senderUserId_idx');
            $table->renameIndex('idx_mensagem_direta_nome_aluno', 'DirectMessage_studentName_idx');
            $table->renameIndex('idx_mensagem_direta_aluno_usuario_id', 'DirectMessage_studentUserId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('nome_remetente', 'senderName');
            $table->renameColumn('papel_remetente', 'senderRole');
            $table->renameColumn('texto', 'text');
            $table->renameColumn('registrado_em_texto', 'timestamp');
            $table->renameColumn('enviada_em', 'enviadaEm');
            $table->renameColumn('remetente_usuario_id', 'senderUserId');
            $table->renameColumn('aluno_usuario_id', 'studentUserId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('modelo_documento', function (Blueprint $table): void {
            $table->renameIndex('idx_modelo_documento_inativado_em', 'documenttemplate_inativadoem_idx');
            $table->renameIndex('idx_modelo_documento_status', 'documenttemplate_status_idx');
            $table->renameIndex('idx_modelo_documento_atualizado_por_usuario_id', 'documenttemplate_updatedby_fkey');
            $table->renameColumn('tipo', 'type');
            $table->renameColumn('nome_instituicao', 'institutionName');
            $table->renameColumn('caminho_logo_instituicao', 'institutionLogoPath');
            $table->renameColumn('signatarios', 'signatories');
            $table->renameColumn('texto_rodape', 'footerText');
            $table->renameColumn('html_personalizado', 'customHtml');
            $table->renameColumn('atualizado_por_usuario_id', 'updatedByUserId');
            $table->renameColumn('atualizado_em', 'updatedAt');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('entrega_exercicio', function (Blueprint $table): void {
            $table->renameIndex('idx_entrega_exercicio_exercicio_id', 'ExerciseSubmission_exerciseId_idx');
            $table->renameIndex('idx_entrega_exercicio_inativado_em', 'exercisesubmission_inativadoem_idx');
            $table->renameIndex('idx_entrega_exercicio_nome_aluno', 'ExerciseSubmission_studentName_idx');
            $table->renameIndex('idx_entrega_exercicio_usuario_id', 'ExerciseSubmission_userId_idx');
            $table->renameColumn('exercicio_id', 'exerciseId');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('texto_entrega', 'submissionText');
            $table->renameColumn('url_arquivo', 'fileUrl');
            $table->renameColumn('nome_arquivo', 'fileName');
            $table->renameColumn('enviado_em_texto', 'submittedAt');
            $table->renameColumn('nota', 'score');
            $table->renameColumn('devolutiva', 'feedback');
            $table->renameColumn('corrigido_em_texto', 'gradedAt');
            $table->renameColumn('corrigido_por', 'gradedBy');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('mensagem_forum', function (Blueprint $table): void {
            $table->renameIndex('idx_mensagem_forum_curso_id', 'ForumMessage_courseId_idx');
            $table->renameIndex('idx_mensagem_forum_enviada_em', 'forummessage_enviadaem_idx');
            $table->renameIndex('idx_mensagem_forum_inativado_em', 'forummessage_inativadoem_idx');
            $table->renameIndex('idx_mensagem_forum_remetente_usuario_id', 'ForumMessage_senderUserId_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('nome_remetente', 'senderName');
            $table->renameColumn('papel_remetente', 'senderRole');
            $table->renameColumn('texto', 'text');
            $table->renameColumn('registrado_em_texto', 'timestamp');
            $table->renameColumn('enviada_em', 'enviadaEm');
            $table->renameColumn('curtidas', 'likes');
            $table->renameColumn('curtido_por', 'likedBy');
            $table->renameColumn('remetente_usuario_id', 'senderUserId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('aula', function (Blueprint $table): void {
            $table->renameIndex('idx_aula_curso_id', 'Lesson_courseId_idx');
            $table->renameIndex('idx_aula_inativado_em', 'lesson_inativadoem_idx');
            $table->renameIndex('idx_aula_status', 'lesson_status_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('duracao', 'duration');
            $table->renameColumn('url_video', 'videoUrl');
            $table->renameColumn('conteudo', 'content');
            $table->renameColumn('ordem', 'lesson_order');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('documento_aula', function (Blueprint $table): void {
            $table->renameIndex('idx_documento_aula_inativado_em', 'lessondocument_inativadoem_idx');
            $table->renameIndex('idx_documento_aula_aula_id', 'LessonDocument_lessonId_idx');
            $table->renameIndex('idx_documento_aula_status', 'lessondocument_status_idx');
            $table->renameColumn('aula_id', 'lessonId');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('tipo', 'type');
            $table->renameColumn('tamanho', 'size');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('item_biblioteca', function (Blueprint $table): void {
            $table->renameIndex('idx_item_biblioteca_inativado_em', 'libraryitem_inativadoem_idx');
            $table->renameIndex('idx_item_biblioteca_status', 'libraryitem_status_idx');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('tipo', 'type');
            $table->renameColumn('categoria', 'category');
            $table->renameColumn('descricao', 'description');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('sessao_ao_vivo', function (Blueprint $table): void {
            $table->renameIndex('idx_sessao_ao_vivo_curso_id', 'LiveSession_courseId_idx');
            $table->renameIndex('idx_sessao_ao_vivo_inativado_em', 'livesession_inativadoem_idx');
            $table->renameIndex('idx_sessao_ao_vivo_status', 'livesession_status_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('agendado_para_texto', 'scheduledAt');
            $table->renameColumn('duracao_minutos', 'durationMinutes');
            $table->renameColumn('link_reuniao', 'meetingLink');
            $table->renameColumn('ao_vivo', 'isLive');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('exercicio_pratico', function (Blueprint $table): void {
            $table->renameIndex('idx_exercicio_pratico_curso_id', 'PracticalExercise_courseId_idx');
            $table->renameIndex('idx_exercicio_pratico_inativado_em', 'practicalexercise_inativadoem_idx');
            $table->renameIndex('idx_exercicio_pratico_status', 'practicalexercise_status_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('descricao', 'description');
            $table->renameColumn('instrucoes', 'instructions');
            $table->renameColumn('pontuacao_maxima', 'maxPoints');
            $table->renameColumn('prazo_texto', 'dueDate');
            $table->renameColumn('prazo_em', 'prazoEm');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('avaliacao', function (Blueprint $table): void {
            $table->renameIndex('idx_avaliacao_curso_id', 'Quiz_courseId_idx');
            $table->renameIndex('idx_avaliacao_inativado_em', 'quiz_inativadoem_idx');
            $table->renameIndex('idx_avaliacao_status', 'quiz_status_idx');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('questao_avaliacao', function (Blueprint $table): void {
            $table->renameIndex('idx_questao_avaliacao_inativado_em', 'quizquestion_inativadoem_idx');
            $table->renameIndex('idx_questao_avaliacao_avaliacao_id', 'QuizQuestion_quizId_idx');
            $table->renameColumn('avaliacao_id', 'quizId');
            $table->renameColumn('texto_questao', 'questionText');
            $table->renameColumn('opcoes', 'options');
            $table->renameColumn('indice_opcao_correta', 'correctOptionIndex');
            $table->renameColumn('explicacao', 'explanation');
            $table->renameColumn('mensagem_revisao', 'reviewMessage');
            $table->renameColumn('modulo_recomendado', 'recommendedModule');
            $table->renameColumn('permite_nova_tentativa', 'allowRetry');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('tentativa_avaliacao', function (Blueprint $table): void {
            $table->renameIndex('idx_tentativa_avaliacao_curso_id', 'QuizSubmission_courseId_idx');
            $table->renameIndex('idx_tentativa_avaliacao_enviado_em', 'quizsubmission_enviadoem_idx');
            $table->renameIndex('idx_tentativa_avaliacao_inativado_em', 'quizsubmission_inativadoem_idx');
            $table->renameIndex('idx_tentativa_avaliacao_avaliacao_id', 'QuizSubmission_quizId_idx');
            $table->renameIndex('idx_tentativa_avaliacao_nome_aluno', 'QuizSubmission_studentName_idx');
            $table->renameIndex('idx_tentativa_avaliacao_usuario_id', 'QuizSubmission_userId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('avaliacao_id', 'quizId');
            $table->renameColumn('percentual_nota', 'scorePercent');
            $table->renameColumn('aprovado', 'passed');
            $table->renameColumn('enviado_em_texto', 'submittedAt');
            $table->renameColumn('enviado_em', 'enviadoEm');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('log_seguranca', function (Blueprint $table): void {
            $table->renameIndex('idx_log_seguranca_acao', 'SecurityLog_action_idx');
            $table->renameIndex('idx_log_seguranca_ocorrido_em', 'securitylog_ocorridoem_idx');
            $table->renameIndex('idx_log_seguranca_registrado_em_texto', 'SecurityLog_timestamp_idx');
            $table->renameIndex('idx_log_seguranca_usuario', 'SecurityLog_user_idx');
            $table->renameColumn('registrado_em_texto', 'timestamp');
            $table->renameColumn('ocorrido_em', 'ocorridoEm');
            $table->renameColumn('usuario', 'user');
            $table->renameColumn('papel', 'role');
            $table->renameColumn('ip', 'ipAddress');
            $table->renameColumn('dispositivo', 'device');
            $table->renameColumn('acao', 'action');
            $table->renameColumn('detalhes', 'details');
        });
        Schema::table('sessao_encerrada', function (Blueprint $table): void {
            $table->renameIndex('idx_sessao_encerrada_usuario_id', 'sessaoencerrada_user_idx');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('encerrada_em', 'encerradaEm');
        });
        Schema::table('conteudo_pagina_site', function (Blueprint $table): void {
            $table->renameIndex('idx_conteudo_pagina_site_inativado_em', 'sitepagecontent_inativadoem_idx');
            $table->renameIndex('idx_conteudo_pagina_site_status', 'sitepagecontent_status_idx');
            $table->renameIndex('idx_conteudo_pagina_site_atualizado_por_usuario_id', 'sitepagecontent_updatedby_fkey');
            $table->renameColumn('chave_pagina', 'pageKey');
            $table->renameColumn('conteudo', 'content');
            $table->renameColumn('atualizado_por_usuario_id', 'updatedByUserId');
            $table->renameColumn('atualizado_em', 'updatedAt');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('arquivo_armazenado', function (Blueprint $table): void {
            $table->renameIndex('idx_arquivo_armazenado_inativado_em', 'storedfile_inativadoem_idx');
            $table->renameIndex('idx_arquivo_armazenado_dono_usuario_id', 'StoredFile_ownerUserId_idx');
            $table->renameColumn('nome_original', 'originalName');
            $table->renameColumn('visibilidade', 'visibility');
            $table->renameColumn('dono_usuario_id', 'ownerUserId');
            $table->renameColumn('criado_em', 'createdAt');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('matricula', function (Blueprint $table): void {
            $table->renameIndex('uq_matricula_id', 'StudentEnrollment_id_key');
            $table->renameIndex('idx_matricula_inativado_em', 'studentenrollment_inativadoem_idx');
            $table->renameIndex('idx_matricula_nome_aluno', 'studentenrollment_studentname_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('curso_matriculado_id', 'enrolledCourseId');
            $table->renameColumn('matriculado_em_texto', 'enrolledAt');
            $table->renameColumn('cursos_concluidos_ids', 'completedCourseIds');
            $table->renameColumn('penalidade_desistencia_ate', 'dropOutPenaltyUntil');
            $table->renameColumn('pode_multimatricula', 'canMultiEnroll');
            $table->renameColumn('cursos_extras_ids', 'extraCourseIds');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('progresso_aluno', function (Blueprint $table): void {
            $table->renameIndex('idx_progresso_aluno_curso_id', 'StudentProgress_courseId_idx');
            $table->renameIndex('idx_progresso_aluno_matricula_id', 'StudentProgress_enrollmentId_idx');
            $table->renameIndex('idx_progresso_aluno_inativado_em', 'studentprogress_inativadoem_idx');
            $table->renameIndex('uq_progresso_aluno_usuario_id_curso_id', 'studentprogress_userid_courseid_key');
            $table->renameIndex('idx_progresso_aluno_usuario_id', 'StudentProgress_userId_idx');
            $table->renameColumn('nome_aluno', 'studentName');
            $table->renameColumn('curso_id', 'courseId');
            $table->renameColumn('aulas_concluidas', 'completedLessons');
            $table->renameColumn('sessoes_ao_vivo_assistidas', 'attendedLiveSessions');
            $table->renameColumn('matricula_id', 'enrollmentId');
            $table->renameColumn('usuario_id', 'userId');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });
        Schema::table('configuracao_sistema', function (Blueprint $table): void {
            $table->renameColumn('dados', 'data');
        });
        Schema::table('usuario', function (Blueprint $table): void {
            $table->renameIndex('uq_usuario_cpf', 'user_cpf_key');
            $table->renameIndex('uq_usuario_email', 'User_email_key');
            $table->renameIndex('idx_usuario_inativado_em', 'user_inativadoem_idx');
            $table->renameIndex('idx_usuario_papel', 'User_role_idx');
            $table->renameIndex('idx_usuario_papel_status', 'User_role_status_idx');
            $table->renameIndex('idx_usuario_status', 'User_status_idx');
            $table->renameColumn('nome', 'name');
            $table->renameColumn('senha_hash', 'passwordHash');
            $table->renameColumn('papel', 'role');
            $table->renameColumn('nome_social', 'nomeSocial');
            $table->renameColumn('criado_em', 'createdAt');
            $table->renameColumn('atualizado_em', 'updatedAt');
            $table->renameColumn('area_interesse', 'areaInteresse');
            $table->renameColumn('data_cadastro_texto', 'dataCadastro');
            $table->renameColumn('tentativas_login_falhas', 'failedLoginAttempts');
            $table->renameColumn('bloqueado_ate', 'lockedUntil');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
            $table->renameColumn('versao_da_sessao', 'versaoDaSessao');
        });
        Schema::table('versao_de_conteudo', function (Blueprint $table): void {
            $table->renameIndex('idx_versao_de_conteudo_tipo_referencia_criado_em', 'versaodeconteudo_item_idx');
            $table->renameColumn('criado_por_usuario_id', 'criadoPorUserId');
            $table->renameColumn('criado_em', 'criadoEm');
        });
        Schema::table('evento_webinar', function (Blueprint $table): void {
            $table->renameIndex('idx_evento_webinar_inativado_em', 'webinarevent_inativadoem_idx');
            $table->renameIndex('idx_evento_webinar_status', 'webinarevent_status_idx');
            $table->renameColumn('titulo', 'title');
            $table->renameColumn('data_texto', 'date');
            $table->renameColumn('data_evento', 'dataEvento');
            $table->renameColumn('horario', 'time');
            $table->renameColumn('descricao', 'description');
            $table->renameColumn('imagem', 'image');
            $table->renameColumn('inativado_em', 'inativadoEm');
            $table->renameColumn('inativado_por', 'inativadoPor');
            $table->renameColumn('motivo_inativacao', 'motivoInativacao');
        });

        Schema::rename('solicitacao_academica', 'AcademicRequest');
        Schema::rename('solicitacao_ingresso', 'AdmissionRequest');
        Schema::rename('certificado', 'Certificate');
        Schema::rename('mensagem_chat', 'ChatMessage');
        Schema::rename('evento_cliente', 'ClientEvent');
        Schema::rename('curso', 'Course');
        Schema::rename('historico_slug_curso', 'CourseSlugHistory');
        Schema::rename('mensagem_direta', 'DirectMessage');
        Schema::rename('modelo_documento', 'DocumentTemplate');
        Schema::rename('entrega_exercicio', 'ExerciseSubmission');
        Schema::rename('mensagem_forum', 'ForumMessage');
        Schema::rename('aula', 'Lesson');
        Schema::rename('documento_aula', 'LessonDocument');
        Schema::rename('item_biblioteca', 'LibraryItem');
        Schema::rename('sessao_ao_vivo', 'LiveSession');
        Schema::rename('exercicio_pratico', 'PracticalExercise');
        Schema::rename('avaliacao', 'Quiz');
        Schema::rename('questao_avaliacao', 'QuizQuestion');
        Schema::rename('tentativa_avaliacao', 'QuizSubmission');
        Schema::rename('log_seguranca', 'SecurityLog');
        Schema::rename('sessao_encerrada', 'SessaoEncerrada');
        Schema::rename('conteudo_pagina_site', 'SitePageContent');
        Schema::rename('arquivo_armazenado', 'StoredFile');
        Schema::rename('matricula', 'StudentEnrollment');
        Schema::rename('progresso_aluno', 'StudentProgress');
        Schema::rename('configuracao_sistema', 'SystemSettings');
        Schema::rename('usuario', 'User');
        Schema::rename('versao_de_conteudo', 'VersaoDeConteudo');
        Schema::rename('evento_webinar', 'WebinarEvent');

        foreach (self::FKS as [$tabela, $coluna, $refTabela, $refColuna, $antigo]) {
            [$upd, $del] = self::REGRAS[$antigo];
            DB::statement(
                "ALTER TABLE `{$tabela}` ADD CONSTRAINT `{$antigo}` FOREIGN KEY (`{$coluna}`) "
                ."REFERENCES `{$refTabela}` (`{$refColuna}`) ON UPDATE {$upd} ON DELETE {$del}"
            );
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
