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
        Schema::create('Certificate', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191)->index('certificate_studentname_idx');
            $table->string('courseId', 191)->index('certificate_courseid_idx');
            $table->string('courseTitle', 191);
            $table->string('issueDate', 191);
            $table->double('attendancePercent');
            $table->string('verificationHash', 191);
            $table->string('enrollmentId', 191)->nullable()->index('certificate_enrollmentid_idx');
            $table->string('userId', 191)->nullable()->index('certificate_userid_idx');

            $table->unique(['studentName', 'courseId'], 'certificate_studentname_courseid_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Certificate');
    }
};
