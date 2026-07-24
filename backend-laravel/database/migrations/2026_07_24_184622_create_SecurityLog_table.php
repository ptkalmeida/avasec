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
        Schema::create('SecurityLog', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('timestamp', 191)->index('securitylog_timestamp_idx');
            $table->string('user', 191)->index('securitylog_user_idx');
            $table->string('role', 191);
            $table->string('ipAddress', 191);
            $table->string('device', 191);
            $table->string('action', 191)->index('securitylog_action_idx');
            $table->text('details');
            $table->enum('status', ['SUCCESS', 'WARNING', 'FAILED']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('SecurityLog');
    }
};
