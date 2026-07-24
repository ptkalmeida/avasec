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
        Schema::table('LessonDocument', function (Blueprint $table) {
            $table->foreign(['lessonId'], 'LessonDocument_lessonId_fkey')->references(['id'])->on('Lesson')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LessonDocument', function (Blueprint $table) {
            $table->dropForeign('LessonDocument_lessonId_fkey');
        });
    }
};
