<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 10 — passo 4: idempotência de certificado por (userId, courseId).
 * userId PERMANECE nullable com FK SET NULL: certificado é documento histórico
 * que sobrevive à deleção do aluno (verify público funciona por hash + nome
 * impresso). O índice simples de studentName fica para a busca pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->dropUnique('certificate_studentname_courseid_key');
        });
        Schema::table('Certificate', function (Blueprint $table): void {
            // MySQL: NULLs são distintos em unique — órfãs (userId NULL) coexistem.
            $table->unique(['userId', 'courseId'], 'certificate_userid_courseid_key');
        });
    }

    public function down(): void
    {
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->dropUnique('certificate_userid_courseid_key');
        });
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->unique(['studentName', 'courseId'], 'certificate_studentname_courseid_key');
        });
    }
};
