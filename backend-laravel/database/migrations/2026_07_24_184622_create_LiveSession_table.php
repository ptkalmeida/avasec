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
        Schema::create('LiveSession', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('courseId', 191)->index('livesession_courseid_idx');
            $table->string('title', 191);
            $table->string('scheduledAt', 191);
            $table->integer('durationMinutes');
            $table->text('meetingLink');
            $table->boolean('isLive')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LiveSession');
    }
};
