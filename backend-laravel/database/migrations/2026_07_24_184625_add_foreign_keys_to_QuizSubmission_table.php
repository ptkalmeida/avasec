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
        Schema::table('QuizSubmission', function (Blueprint $table) {
            $table->foreign(['userId'], 'QuizSubmission_userId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('QuizSubmission', function (Blueprint $table) {
            $table->dropForeign('QuizSubmission_userId_fkey');
        });
    }
};
