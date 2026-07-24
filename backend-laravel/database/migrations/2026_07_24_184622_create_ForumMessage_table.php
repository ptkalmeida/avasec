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
        Schema::create('ForumMessage', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('courseId', 191)->index('forummessage_courseid_idx');
            $table->string('senderName', 191);
            $table->enum('senderRole', ['student', 'instructor', 'admin']);
            $table->text('text');
            $table->string('timestamp', 191);
            $table->integer('likes')->default(0);
            $table->json('likedBy');
            $table->string('senderUserId', 191)->nullable()->index('forummessage_senderuserid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ForumMessage');
    }
};
