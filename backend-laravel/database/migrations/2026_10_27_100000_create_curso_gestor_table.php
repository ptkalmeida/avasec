<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Mais de um gestor por curso (decisão do responsável, 27/10/2026).
 *
 * `curso.instrutor_id` continua sendo o gestor responsável (aparece nas listas,
 * no certificado e na vitrine). Esta tabela guarda os DEMAIS gestores que também
 * editam o curso. Quem pode gerenciar um curso é o responsável OU qualquer linha
 * ativa desta tabela — ver `App\Services\GestoresDoCurso`.
 *
 * Nada é apagado (ADR 12): desfazer o vínculo inativa a linha (`inativado_em`),
 * e vincular de novo reativa a mesma linha — por isso a chave única (curso_id,
 * usuario_id) vale mesmo com linhas inativas. As chaves estrangeiras são
 * RESTRICT, como no resto do schema desde a ADR 12.
 *
 * Nome em português e snake_case (ADR 024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_gestor', function (Blueprint $table) {
            $table->id();
            $table->string('curso_id', 191);
            $table->string('usuario_id', 191);
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('inativado_em')->nullable();
            $table->string('inativado_por', 191)->nullable();
            $table->string('motivo_inativacao', 500)->nullable();

            $table->unique(['curso_id', 'usuario_id'], 'uq_curso_gestor_curso_usuario');
            $table->index('usuario_id', 'idx_curso_gestor_usuario_id');

            $table->foreign('curso_id', 'fk_curso_gestor_curso_id')->references('id')->on('curso')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('usuario_id', 'fk_curso_gestor_usuario_id')->references('id')->on('usuario')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_gestor');
    }
};
