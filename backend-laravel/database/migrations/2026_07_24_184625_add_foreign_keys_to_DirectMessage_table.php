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
        Schema::table('DirectMessage', function (Blueprint $table) {
            $table->foreign(['senderUserId'], 'DirectMessage_senderUserId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
            $table->foreign(['studentUserId'], 'DirectMessage_studentUserId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('DirectMessage', function (Blueprint $table) {
            $table->dropForeign('DirectMessage_senderUserId_fkey');
            $table->dropForeign('DirectMessage_studentUserId_fkey');
        });
    }
};
