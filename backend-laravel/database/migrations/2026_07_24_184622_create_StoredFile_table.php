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
        Schema::create('StoredFile', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('originalName', 191);
            $table->enum('visibility', ['public', 'private']);
            $table->string('ownerUserId', 191)->nullable()->index('storedfile_owneruserid_idx');
            $table->dateTime('createdAt', 3)->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('StoredFile');
    }
};
