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
        Schema::create('StudentProgress', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191);
            $table->string('courseId', 191)->index('studentprogress_courseid_idx');
            $table->json('completedLessons');
            $table->json('attendedLiveSessions');
            $table->string('enrollmentId', 191)->nullable()->index('studentprogress_enrollmentid_idx');
            $table->string('userId', 191)->nullable()->index('studentprogress_userid_idx');

            $table->unique(['studentName', 'courseId'], 'studentprogress_studentname_courseid_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('StudentProgress');
    }
};
