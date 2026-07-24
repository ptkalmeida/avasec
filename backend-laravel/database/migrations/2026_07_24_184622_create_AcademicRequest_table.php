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
        Schema::create('AcademicRequest', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191)->index('academicrequest_studentname_idx');
            $table->enum('type', ['certificado', 'historico', 'matricula', 'outro']);
            $table->text('description');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index('academicrequest_status_idx');
            $table->string('submittedAt', 191);
            $table->string('courseTitle', 191)->nullable();
            $table->string('userId', 191)->nullable()->index('academicrequest_userid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('AcademicRequest');
    }
};
