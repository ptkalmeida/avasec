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
        Schema::create('DirectMessage', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('studentName', 191)->index('directmessage_studentname_idx');
            $table->string('senderName', 191);
            $table->enum('senderRole', ['student', 'instructor', 'admin']);
            $table->text('text');
            $table->string('timestamp', 191);
            $table->string('senderUserId', 191)->nullable()->index('directmessage_senderuserid_idx');
            $table->string('studentUserId', 191)->nullable()->index('directmessage_studentuserid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('DirectMessage');
    }
};
