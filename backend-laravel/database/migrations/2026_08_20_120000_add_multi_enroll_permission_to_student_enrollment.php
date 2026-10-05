<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matrícula múltipla concorrente (concedida pelo Admin Superior por aluno):
 * `canMultiEnroll` é a permissão persistida; `extraCourseIds` guarda os cursos
 * ativos ALÉM do principal (`enrolledCourseId`), que continua representando o
 * curso "primário" para todo o código existente. Sem `enrolledAt` por item —
 * mesma simplificação já usada em `completedCourseIds`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->boolean('canMultiEnroll')->default(false)->after('dropOutPenaltyUntil');
            $table->json('extraCourseIds')->nullable()->after('canMultiEnroll');
        });
    }

    public function down(): void
    {
        Schema::table('StudentEnrollment', function (Blueprint $table): void {
            $table->dropColumn(['canMultiEnroll', 'extraCourseIds']);
        });
    }
};
