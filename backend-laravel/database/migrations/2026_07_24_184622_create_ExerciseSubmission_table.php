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
        Schema::create('ExerciseSubmission', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('exerciseId', 191)->index('exercisesubmission_exerciseid_idx');
            $table->string('studentName', 191)->index('exercisesubmission_studentname_idx');
            $table->text('submissionText');
            $table->text('fileUrl')->nullable();
            $table->string('fileName', 191)->nullable();
            $table->string('submittedAt', 191);
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision'])->default('pending');
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable();
            $table->string('gradedAt', 191)->nullable();
            $table->string('gradedBy', 191)->nullable();
            $table->string('userId', 191)->nullable()->index('exercisesubmission_userid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ExerciseSubmission');
    }
};
