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
        Schema::create('StudentEnrollment', function (Blueprint $table) {
            $table->string('studentName', 191)->primary();
            $table->string('enrolledCourseId', 191)->nullable();
            $table->string('enrolledAt', 191)->nullable();
            $table->json('completedCourseIds');
            $table->string('dropOutPenaltyUntil', 191)->nullable();
            $table->string('id', 191)->nullable()->unique('studentenrollment_id_key');
            $table->string('userId', 191)->nullable()->unique('studentenrollment_userid_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('StudentEnrollment');
    }
};
