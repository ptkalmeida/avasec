<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Área de gerenciamento de templates de documentos (certificado, histórico
 * escolar) pelo Admin Superior. PK = `type` (ex.: 'certificado', 'historico')
 * — uma linha por tipo de documento. Sem linha salva ainda = usa os valores
 * padrão hardcoded que já existiam (services devem cair no default).
 *
 * `customHtml` presente e não-vazio = modo "layout livre": o HTML substitui
 * o template estruturado por completo. Ausente = modo "padrão estruturado"
 * usando institutionName/signatories/footerText.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DocumentTemplate', function (Blueprint $table) {
            $table->string('type', 50)->primary();
            $table->string('institutionName', 191)->nullable();
            $table->string('institutionLogoPath', 191)->nullable();
            $table->json('signatories')->nullable();
            $table->text('footerText')->nullable();
            $table->longText('customHtml')->nullable();
            $table->string('updatedByUserId', 191)->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('updatedByUserId', 'documenttemplate_updatedby_fkey')
                ->references('id')->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('DocumentTemplate');
    }
};
