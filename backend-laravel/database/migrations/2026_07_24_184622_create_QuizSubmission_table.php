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
        Schema::create('QuizSubmission', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191)->index('quizsubmission_studentname_idx');
            $table->string('courseId', 191)->index('quizsubmission_courseid_idx');
            $table->string('quizId', 191)->index('quizsubmission_quizid_idx');
            $table->double('scorePercent');
            $table->boolean('passed');
            $table->string('submittedAt', 191);
            $table->string('userId', 191)->nullable()->index('quizsubmission_userid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('QuizSubmission');
    }
};
