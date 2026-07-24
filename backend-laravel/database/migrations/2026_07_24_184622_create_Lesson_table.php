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
        Schema::create('Lesson', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('courseId', 191)->index('lesson_courseid_idx');
            $table->string('title', 191);
            $table->string('duration', 191);
            $table->text('videoUrl')->nullable();
            $table->text('content')->nullable();
            $table->integer('lesson_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Lesson');
    }
};
