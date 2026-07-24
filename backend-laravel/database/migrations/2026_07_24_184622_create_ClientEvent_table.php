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
        Schema::create('ClientEvent', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->dateTime('createdAt', 3)->useCurrent()->index('clientevent_createdat_idx');
            $table->string('user', 191)->index('clientevent_user_idx');
            $table->string('role', 191);
            $table->string('ipAddress', 191);
            $table->string('device', 191);
            $table->string('action', 191);
            $table->text('details');
            $table->enum('status', ['SUCCESS', 'WARNING', 'FAILED'])->default('SUCCESS');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ClientEvent');
    }
};
