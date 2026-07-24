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
        Schema::create('LibraryItem', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('title', 191);
            $table->enum('type', ['pdf', 'video', 'link']);
            $table->string('category', 191);
            $table->text('description')->nullable();
            $table->text('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LibraryItem');
    }
};
