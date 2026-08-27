<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo editável das páginas públicas do portal (O Projeto, Notícias,
 * Dúvidas, Calendário, Orientações) — só o Admin Superior edita, qualquer
 * visitante lê. PK = `pageKey` (ex.: 'orientacoes') — uma linha por página.
 *
 * Sem linha salva = o service devolve os defaults hardcoded (que espelham o
 * conteúdo que já estava fixo nos componentes React), então o site público
 * nunca aparece vazio por falta de edição.
 *
 * `content` guarda a estrutura da página (cabeçalho + lista de itens) como
 * JSON. É deliberadamente texto puro, sem HTML: o conteúdo é renderizado em
 * página pública, e aceitar HTML de entrada abriria XSS armazenado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SitePageContent', function (Blueprint $table) {
            $table->string('pageKey', 50)->primary();
            $table->json('content');
            $table->string('updatedByUserId', 191)->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('updatedByUserId', 'sitepagecontent_updatedby_fkey')
                ->references('id')->on('User')->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SitePageContent');
    }
};
