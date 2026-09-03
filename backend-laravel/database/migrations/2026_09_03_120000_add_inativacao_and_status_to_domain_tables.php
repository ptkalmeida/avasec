<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 12 — nada é apagado.
 *
 * Antes disto o sistema apagava fisicamente em 14 pontos, e as chaves
 * estrangeiras estavam em ON DELETE CASCADE: apagar UM usuário destruía notas,
 * entregas corrigidas, progresso, matrícula e requerimentos, e deixava o
 * certificado apontando para ninguém (SET NULL).
 *
 * Dois eixos, de propósito separados — existir e quem-pode-ver são perguntas
 * diferentes:
 *
 *   inativadoEm       NULL = no ar | data = fora do ar, registro intacto
 *   inativadoPor      quem tirou do ar (userId)
 *   motivoInativacao  por quê
 *   status            1 publicado (aluno) | 2 restrito (gestor+admin) | 3 rascunho (admin)
 *
 * `status` só entra em tabela de CONTEÚDO, onde audiência faz sentido. Em
 * `QuizSubmission` ou `StudentProgress` quem vê é decidido por pertencimento
 * (CourseAccess), não por escada de papel.
 *
 * `User` NÃO recebe `status`: já tem uma coluna com esse nome, enum
 * ('active','blocked','pending_confirmation'), que é o estado da CONTA. Sobrepor
 * trocaria o significado de um campo em uso pela autenticação.
 */
return new class extends Migration
{
    /** Tudo que pode sair do ar e não pode ser apagado. */
    private const TABELAS_INATIVAVEIS = [
        'User',
        'Course', 'Lesson', 'LessonDocument', 'LiveSession',
        'Quiz', 'QuizQuestion', 'QuizSubmission',
        'PracticalExercise', 'ExerciseSubmission',
        'ForumMessage', 'ChatMessage', 'DirectMessage',
        'WebinarEvent', 'LibraryItem',
        'Certificate',
        'StudentEnrollment', 'StudentProgress',
        'AcademicRequest', 'AdmissionRequest',
        'DocumentTemplate', 'SitePageContent', 'StoredFile',
    ];

    /** Conteúdo exibível, onde a escada de audiência tem consumidor. */
    private const TABELAS_COM_STATUS = [
        'Course', 'Lesson', 'LessonDocument', 'LiveSession',
        'Quiz', 'PracticalExercise',
        'WebinarEvent', 'LibraryItem',
        'DocumentTemplate', 'SitePageContent',
    ];

    public function up(): void
    {
        foreach (self::TABELAS_INATIVAVEIS as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'inativadoEm')) {
                continue;
            }
            Schema::table($tabela, function (Blueprint $table) use ($tabela): void {
                // Índice porque TODA listagem passa a filtrar por esta coluna.
                $table->timestamp('inativadoEm')->nullable()->index(strtolower($tabela).'_inativadoem_idx');
                // Sem FK para User de propósito: se o autor da inativação fosse
                // apagado, um CASCADE levaria embora o registro de quem agiu —
                // e é justamente esse registro que a auditoria precisa guardar.
                $table->string('inativadoPor', 191)->nullable();
                $table->string('motivoInativacao', 500)->nullable();
            });
        }

        foreach (self::TABELAS_COM_STATUS as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'status')) {
                continue;
            }
            Schema::table($tabela, function (Blueprint $table) use ($tabela): void {
                // Default 1: todo conteúdo que já existe segue publicado. Um
                // default diferente sumiria com o curso da escola na migration.
                $table->unsignedTinyInteger('status')->default(1)
                    ->index(strtolower($tabela).'_status_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS_COM_STATUS as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'status')) {
                Schema::table($tabela, static function (Blueprint $table): void {
                    $table->dropColumn('status');
                });
            }
        }

        foreach (self::TABELAS_INATIVAVEIS as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'inativadoEm')) {
                continue;
            }
            Schema::table($tabela, static function (Blueprint $table): void {
                $table->dropColumn(['inativadoEm', 'inativadoPor', 'motivoInativacao']);
            });
        }
    }
};
