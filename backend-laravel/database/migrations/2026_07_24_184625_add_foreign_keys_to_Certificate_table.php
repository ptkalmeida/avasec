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
        Schema::table('Certificate', function (Blueprint $table) {
            $table->foreign(['enrollmentId'], 'Certificate_enrollmentId_fkey')->references(['id'])->on('StudentEnrollment')->onUpdate('cascade')->onDelete('set null');
            $table->foreign(['userId'], 'Certificate_userId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Certificate', function (Blueprint $table) {
            $table->dropForeign('Certificate_enrollmentId_fkey');
            $table->dropForeign('Certificate_userId_fkey');
        });
    }
};
