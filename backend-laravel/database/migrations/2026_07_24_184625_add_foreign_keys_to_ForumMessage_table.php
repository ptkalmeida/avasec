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
        Schema::table('ForumMessage', function (Blueprint $table) {
            $table->foreign(['senderUserId'], 'ForumMessage_senderUserId_fkey')->references(['id'])->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ForumMessage', function (Blueprint $table) {
            $table->dropForeign('ForumMessage_senderUserId_fkey');
        });
    }
};
