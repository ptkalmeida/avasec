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
        Schema::table('AcademicRequest', function (Blueprint $table) {
            $table->foreign(['userId'], 'AcademicRequest_userId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('AcademicRequest', function (Blueprint $table) {
            $table->dropForeign('AcademicRequest_userId_fkey');
        });
    }
};
