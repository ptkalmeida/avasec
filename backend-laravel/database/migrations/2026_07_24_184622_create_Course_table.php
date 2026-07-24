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
        Schema::create('Course', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('title', 191);
            $table->text('description');
            $table->string('category', 191);
            $table->text('thumbnail');
            $table->string('instructorName', 191)->index('course_instructorname_idx');
            $table->text('coverImage')->nullable();
            $table->enum('courseType', ['fixo', 'ao_vivo'])->nullable();
            $table->boolean('hasChat')->nullable();
            $table->integer('minAttendance')->nullable();
            $table->string('contractExpirationDate', 191)->nullable();
            $table->string('areaTematica', 191)->nullable();
            $table->integer('cargaHoraria')->nullable();
            $table->string('modalidade', 191)->nullable();
            $table->string('nivel', 191)->nullable();
            $table->boolean('emiteCertificado')->nullable();
            $table->string('statusCurso', 191)->nullable();
            $table->string('instructorId', 191)->nullable()->index('course_instructorid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Course');
    }
};
