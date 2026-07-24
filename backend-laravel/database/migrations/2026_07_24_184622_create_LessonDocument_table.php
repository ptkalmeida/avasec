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
        Schema::create('LessonDocument', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('lessonId', 191)->index('lessondocument_lessonid_idx');
            $table->string('title', 191);
            $table->enum('type', ['pdf', 'doc', 'url', 'drive', 'outro']);
            $table->text('url');
            $table->string('size', 191)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LessonDocument');
    }
};
