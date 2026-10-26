<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de versões do que o responsável edita e que, ao salvar, SUBSTITUÍA o
 * texto anterior: páginas do site, modelos de documento e aulas (ADR 12, ADR 021).
 *
 * Cada linha é o conteúdo que existia ANTES de uma alteração — a versão que ia se
 * perder. Só se acrescenta: não há UPDATE nem DELETE nesta tabela, e restaurar uma
 * versão grava uma linha nova (a do conteúdo que estava no ar), em vez de mexer
 * na antiga.
 *
 * `tipo` + `referencia` identificam o item: `pagina_site`/`o-ava`,
 * `modelo_documento`/`certificado`, `aula`/<id da aula>. Sem chave estrangeira de
 * propósito: a referência aponta para três tabelas diferentes, e a versão tem de
 * sobreviver ao item (uma aula inativada continua com o histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VersaoDeConteudo', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->string('tipo', 30);
            $table->string('referencia', 191);
            $table->longText('conteudo');
            $table->string('origem', 20)->default('edicao');
            $table->string('criadoPorUserId', 191)->nullable();
            $table->timestamp('criadoEm')->useCurrent();

            $table->index(['tipo', 'referencia', 'criadoEm'], 'versaodeconteudo_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VersaoDeConteudo');
    }
};
