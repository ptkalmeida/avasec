<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 10 — passo 3: userId vira a chave do domínio de matrículas.
 * StudentEnrollment troca a PK de studentName para userId (NOT NULL, CASCADE);
 * StudentProgress troca o unique (studentName,courseId) por (userId,courseId);
 * AdmissionRequest/AcademicRequest ganham userId NOT NULL + CASCADE.
 * studentName permanece em todas como coluna de exibição (snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- StudentEnrollment: PK studentName -> PK userId (ordem MySQL obrigatória) ---
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropForeign('StudentEnrollment_userId_fkey');
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropUnique('studentenrollment_userid_key');
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropPrimary();
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable(false)->change();
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->primary('userId');
            $table->index('studentName', 'studentenrollment_studentname_idx');
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->foreign(['userId'], 'StudentEnrollment_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
        });

        // --- StudentProgress: unique por (userId, courseId) ---
        Schema::table('StudentProgress', function (Blueprint $table): void {
            $table->dropForeign('StudentProgress_userId_fkey');
            $table->dropUnique('studentprogress_studentname_courseid_key');
        });
        Schema::table('StudentProgress', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable(false)->change();
        });
        Schema::table('StudentProgress', function (Blueprint $table): void {
            $table->unique(['userId', 'courseId'], 'studentprogress_userid_courseid_key');
            $table->foreign(['userId'], 'StudentProgress_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
        });

        // --- AdmissionRequest ---
        Schema::table('AdmissionRequest', function (Blueprint $table): void {
            $table->dropForeign('AdmissionRequest_userId_fkey');
            $table->dropIndex('admissionrequest_studentname_courseid_status_idx');
        });
        Schema::table('AdmissionRequest', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable(false)->change();
        });
        Schema::table('AdmissionRequest', function (Blueprint $table): void {
            $table->index(['userId', 'courseId', 'status'], 'admissionrequest_userid_courseid_status_idx');
            $table->foreign(['userId'], 'AdmissionRequest_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
        });

        // --- AcademicRequest ---
        Schema::table('AcademicRequest', function (Blueprint $table): void {
            $table->dropForeign('AcademicRequest_userId_fkey');
        });
        Schema::table('AcademicRequest', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable(false)->change();
        });
        Schema::table('AcademicRequest', function (Blueprint $table): void {
            $table->foreign(['userId'], 'AcademicRequest_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('AcademicRequest', function (Blueprint $table): void {
            $table->dropForeign('AcademicRequest_userId_fkey');
        });
        Schema::table('AcademicRequest', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable()->change();
            $table->foreign(['userId'], 'AcademicRequest_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });

        Schema::table('AdmissionRequest', function (Blueprint $table): void {
            $table->dropForeign('AdmissionRequest_userId_fkey');
            $table->dropIndex('admissionrequest_userid_courseid_status_idx');
        });
        Schema::table('AdmissionRequest', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable()->change();
            $table->index(['studentName', 'courseId', 'status'], 'admissionrequest_studentname_courseid_status_idx');
            $table->foreign(['userId'], 'AdmissionRequest_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });

        Schema::table('StudentProgress', function (Blueprint $table): void {
            $table->dropForeign('StudentProgress_userId_fkey');
            $table->dropUnique('studentprogress_userid_courseid_key');
        });
        Schema::table('StudentProgress', function (Blueprint $table): void {
            $table->string('userId', 191)->nullable()->change();
            $table->unique(['studentName', 'courseId'], 'studentprogress_studentname_courseid_key');
            $table->foreign(['userId'], 'StudentProgress_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });

        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropForeign('StudentEnrollment_userId_fkey');
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropPrimary();
            $table->dropIndex('studentenrollment_studentname_idx');
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->primary('studentName');
            $table->string('userId', 191)->nullable()->change();
        });
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->unique('userId', 'studentenrollment_userid_key');
            $table->foreign(['userId'], 'StudentEnrollment_userId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }
};
