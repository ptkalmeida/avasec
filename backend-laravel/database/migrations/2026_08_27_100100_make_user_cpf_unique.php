<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF passa a ser identificador de login do aluno (ADR 11), então precisa ser
 * único. Nome da constraint segue a convenção de `user_email_key`.
 *
 * A coluna segue `nullable`: em MySQL um índice único aceita múltiplos NULL,
 * então as contas de staff (que entram por e-mail e não têm CPF) continuam
 * válidas sem exigir backfill. O que o índice impede é DOIS cadastros com o
 * mesmo CPF preenchido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->unique('cpf', 'user_cpf_key');
        });
    }

    public function down(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->dropUnique('user_cpf_key');
        });
    }
};
