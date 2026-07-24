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
        Schema::table('Lesson', function (Blueprint $table) {
            $table->foreign(['courseId'], 'Lesson_courseId_fkey')->references(['id'])->on('Course')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Lesson', function (Blueprint $table) {
            $table->dropForeign('Lesson_courseId_fkey');
        });
    }
};
