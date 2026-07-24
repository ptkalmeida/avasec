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
        Schema::table('QuizQuestion', function (Blueprint $table) {
            $table->foreign(['quizId'], 'QuizQuestion_quizId_fkey')->references(['id'])->on('Quiz')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('QuizQuestion', function (Blueprint $table) {
            $table->dropForeign('QuizQuestion_quizId_fkey');
        });
    }
};
