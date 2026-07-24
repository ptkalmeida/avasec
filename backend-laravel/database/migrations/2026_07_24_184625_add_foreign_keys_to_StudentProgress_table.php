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
        Schema::table('StudentProgress', function (Blueprint $table) {
            $table->foreign(['enrollmentId'], 'StudentProgress_enrollmentId_fkey')->references(['id'])->on('StudentEnrollment')->onUpdate('cascade')->onDelete('set null');
            $table->foreign(['userId'], 'StudentProgress_userId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('StudentProgress', function (Blueprint $table) {
            $table->dropForeign('StudentProgress_enrollmentId_fkey');
            $table->dropForeign('StudentProgress_userId_fkey');
        });
    }
};
