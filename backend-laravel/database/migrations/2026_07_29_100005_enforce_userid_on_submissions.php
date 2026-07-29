<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 10 — passo 5: submissões de quiz/exercício sempre com dono (userId
 * NOT NULL, CASCADE). studentName permanece como snapshot de exibição.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['QuizSubmission', 'ExerciseSubmission'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_userId_fkey");
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('userId', 191)->nullable(false)->change();
            });
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreign(['userId'], "{$tableName}_userId_fkey")
                    ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach (['QuizSubmission', 'ExerciseSubmission'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_userId_fkey");
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('userId', 191)->nullable()->change();
            });
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreign(['userId'], "{$tableName}_userId_fkey")
                    ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
            });
        }
    }
};
