<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessão que pode ser encerrada no servidor (ADR 022).
 *
 * O JWT valia 12 h e nada o cancelava: "Sair" só apagava o cookie, e trocar a senha
 * não derrubava quem já tinha o token. Duas peças, ambas só aditivas:
 *
 * - `User.versaoDaSessao`: número que o token carrega (`sv`). Trocar a senha, redefini-la,
 *   bloquear ou inativar a conta soma 1, e todo token com número menor deixa de valer.
 *   Token emitido antes desta migration não traz o número e vale como 0 — continua
 *   valendo até a primeira troca, então ninguém é deslogado por causa do deploy.
 * - `SessaoEncerrada`: o "Sair" de UM aparelho. Guarda o `jti` do token que saiu, sem
 *   derrubar a sessão da mesma pessoa em outro aparelho. Só se acrescenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('User', 'versaoDaSessao')) {
            Schema::table('User', function (Blueprint $table) {
                $table->unsignedInteger('versaoDaSessao')->default(0);
            });
        }

        Schema::create('SessaoEncerrada', function (Blueprint $table) {
            $table->string('jti', 64)->primary();
            $table->string('userId', 191);
            $table->timestamp('encerradaEm')->useCurrent();

            $table->index('userId', 'sessaoencerrada_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SessaoEncerrada');
        // A coluna `versaoDaSessao` fica: dropColumn em tabela de domínio é proibido pela ADR 12.
    }
};
