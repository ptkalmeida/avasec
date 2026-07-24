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
        Schema::create('AdmissionRequest', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191)->index('admissionrequest_studentname_idx');
            $table->string('courseId', 191)->index('admissionrequest_courseid_idx');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('submittedAt', 191);
            $table->string('userId', 191)->nullable()->index('admissionrequest_userid_idx');

            $table->index(['studentName', 'courseId', 'status'], 'admissionrequest_studentname_courseid_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('AdmissionRequest');
    }
};
