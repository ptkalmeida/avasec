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
        Schema::table('ExerciseSubmission', function (Blueprint $table) {
            $table->foreign(['exerciseId'], 'ExerciseSubmission_exerciseId_fkey')->references(['id'])->on('PracticalExercise')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['userId'], 'ExerciseSubmission_userId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ExerciseSubmission', function (Blueprint $table) {
            $table->dropForeign('ExerciseSubmission_exerciseId_fkey');
            $table->dropForeign('ExerciseSubmission_userId_fkey');
        });
    }
};
