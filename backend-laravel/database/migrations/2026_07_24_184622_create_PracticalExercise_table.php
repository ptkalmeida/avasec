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
        Schema::create('PracticalExercise', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('courseId', 191)->index('practicalexercise_courseid_idx');
            $table->string('title', 191);
            $table->text('description');
            $table->text('instructions');
            $table->integer('maxPoints');
            $table->string('dueDate', 191)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PracticalExercise');
    }
};
