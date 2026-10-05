<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `Certificate.issueDate` era string `d/m/Y` — ordenação lexicográfica é
 * incorreta entre anos (ex.: "01/01/2027" < "31/12/2026"). Passa a coluna DATE
 * real; a API continua serializando como d/m/Y via cast do model (contrato
 * intocado). Converte os valores existentes com STR_TO_DATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->date('issueDate_tmp')->nullable();
        });
        DB::statement("UPDATE `Certificate` SET issueDate_tmp = STR_TO_DATE(issueDate, '%d/%m/%Y')");
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->dropColumn('issueDate');
        });
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->renameColumn('issueDate_tmp', 'issueDate');
        });
    }

    public function down(): void
    {
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->string('issueDate_tmp', 191)->nullable();
        });
        DB::statement("UPDATE `Certificate` SET issueDate_tmp = DATE_FORMAT(issueDate, '%d/%m/%Y')");
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->dropColumn('issueDate');
        });
        Schema::table('Certificate', function (Blueprint $table): void {
            $table->renameColumn('issueDate_tmp', 'issueDate');
        });
    }
};
