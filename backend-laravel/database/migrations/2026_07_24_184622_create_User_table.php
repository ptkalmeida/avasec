<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('User', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('name', 191);
            $table->string('email', 191)->unique('user_email_key');
            $table->string('passwordHash', 191);
            $table->enum('role', ['student', 'instructor', 'admin'])->default('student')->index('user_role_idx');
            $table->string('cpf', 191)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);
            $table->string('municipio', 191)->nullable();
            $table->string('uf', 191)->nullable();
            $table->string('areaInteresse', 191)->nullable();
            $table->string('dataCadastro', 191)->nullable();
            $table->integer('failedLoginAttempts')->default(0);
            $table->dateTime('lockedUntil', 3)->nullable();
            $table->enum('status', ['active', 'blocked', 'pending_confirmation'])->default('active')->index('user_status_idx');

            $table->index(['role', 'status'], 'user_role_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('User');
    }
};
