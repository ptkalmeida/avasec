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
        Schema::table('ChatMessage', function (Blueprint $table) {
            $table->foreign(['senderUserId'], 'ChatMessage_senderUserId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ChatMessage', function (Blueprint $table) {
            $table->dropForeign('ChatMessage_senderUserId_fkey');
        });
    }
};
