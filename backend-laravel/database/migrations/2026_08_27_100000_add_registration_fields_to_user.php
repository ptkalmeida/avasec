<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados cadastrais completos no cadastro de aluno (ADR 11): celular, CEP,
 * endereço, nome social e identidade (RG).
 *
 * Todas `nullable` seguindo o padrão já usado por `municipio`/`uf`/
 * `areaInteresse` — as contas de staff que existem hoje não têm esses dados e
 * não devem ser invalidadas por isso. A obrigatoriedade vive na validação do
 * cadastro de ALUNO (AuthController), não no schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->string('celular', 191)->nullable()->after('cpf');
            $table->string('cep', 191)->nullable()->after('celular');
            $table->string('endereco', 191)->nullable()->after('cep');
            $table->string('nomeSocial', 191)->nullable()->after('endereco');
            $table->string('identidade', 191)->nullable()->after('nomeSocial');
        });
    }

    public function down(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->dropColumn(['celular', 'cep', 'endereco', 'nomeSocial', 'identidade']);
        });
    }
};
