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
        Schema::create('QuizQuestion', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('quizId', 191)->index('quizquestion_quizid_idx');
            $table->text('questionText');
            $table->json('options');
            $table->integer('correctOptionIndex');
            $table->text('explanation')->nullable();
            $table->text('reviewMessage')->nullable();
            $table->string('recommendedModule', 191)->nullable();
            $table->boolean('allowRetry')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('QuizQuestion');
    }
};
