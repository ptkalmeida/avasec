<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Histórico escolar emitido pelo admin (decisão do responsável, 27/10/2026): o
 * admin emite e o documento passa a aparecer, sozinho, na área do aluno para baixar.
 *
 * Cada emissão é uma linha NOVA (ADR 12: acrescentar, nunca substituir). `dados`
 * guarda a fotografia do que valia no dia (nome e cursos concluídos com carga
 * horária, frequência e data), para o documento emitido não mudar depois. Refazer a
 * emissão acrescenta outra linha; tirar uma do ar inativa, com quem e por quê.
 *
 * Nome em português e snake_case (ADR 024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_emitido', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id', 191);
            $table->string('emitido_por', 191)->nullable();
            $table->timestamp('emitido_em')->useCurrent();
            $table->json('dados');
            $table->timestamp('inativado_em')->nullable();
            $table->string('inativado_por', 191)->nullable();
            $table->string('motivo_inativacao', 500)->nullable();

            $table->index(['usuario_id', 'emitido_em'], 'idx_historico_emitido_usuario_id_emitido_em');

            $table->foreign('usuario_id', 'fk_historico_emitido_usuario_id')->references('id')->on('usuario')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_emitido');
    }
};
