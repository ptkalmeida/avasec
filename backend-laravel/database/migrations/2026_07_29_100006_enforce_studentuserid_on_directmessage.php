<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 10 — passo 6: a thread de DM pertence a um aluno real (studentUserId
 * NOT NULL, CASCADE). senderUserId permanece nullable SET NULL — o histórico
 * da conversa sobrevive à deleção do remetente (senderName é snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->dropForeign('DirectMessage_studentUserId_fkey');
        });
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->string('studentUserId', 191)->nullable(false)->change();
        });
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->foreign(['studentUserId'], 'DirectMessage_studentUserId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->dropForeign('DirectMessage_studentUserId_fkey');
        });
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->string('studentUserId', 191)->nullable()->change();
        });
        Schema::table('DirectMessage', function (Blueprint $table): void {
            $table->foreign(['studentUserId'], 'DirectMessage_studentUserId_fkey')
                ->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }
};
