<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * O que o admin passa a editar nos modelos de certificado e de histórico além do
 * cabeçalho, das assinaturas e do rodapé (pedido do responsável, 27/10/2026):
 *
 * - `titulo`: o título do documento ("Certificado de Conclusão").
 * - `texto_corpo`: o texto do corpo, com campos automáticos ({aluno}, {curso}...).
 * - `rotulos_colunas`: os nomes das colunas da tabela do histórico (JSON).
 *
 * Só acrescenta colunas anuláveis: quem nunca editou continua com os textos de
 * fábrica (ADR 12: nada é apagado). Nomes em português e snake_case (ADR 024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelo_documento', function (Blueprint $table) {
            $table->string('titulo', 191)->nullable()->after('nome_instituicao');
            $table->text('texto_corpo')->nullable()->after('titulo');
            $table->json('rotulos_colunas')->nullable()->after('texto_corpo');
        });
    }

    public function down(): void
    {
        Schema::table('modelo_documento', function (Blueprint $table) {
            $table->dropColumn(['titulo', 'texto_corpo', 'rotulos_colunas']);
        });
    }
};
