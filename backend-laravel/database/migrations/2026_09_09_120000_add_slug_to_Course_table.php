<?php

declare(strict_types=1);

use App\Support\CursoSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slug de curso persistido, e o histórico dos aposentados (ADR 13).
 *
 * A coluna nasce anulável, recebe o backfill e SÓ DEPOIS ganha o índice único —
 * na ordem inversa a migration falha no banco de desenvolvimento, que já tem
 * seis cursos, dois deles com títulos parecidos.
 *
 * O backfill lê com SQL cru de propósito: `Course` usa SoftDeletes por
 * `inativadoEm`, e o Eloquent esconderia o curso inativado. Curso inativado
 * continua tendo endereço — o histórico acadêmico dele é consultável (ADR 12) —
 * e o slug dele precisa entrar na conta de unicidade, senão um curso novo
 * tomaria o slug de um antigo e o índice único estouraria depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Course', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->after('title');
        });

        /*
         * Tabela de slugs aposentados. É append-only: renomear um curso acrescenta
         * uma linha, nada é sobrescrito nem apagado (ADR 12). É o que permite ao
         * link salvo antes do rename continuar abrindo o curso certo.
         */
        Schema::create('CourseSlugHistory', function (Blueprint $table) {
            $table->string('slug', 191)->primary();
            $table->string('courseId', 191)->index('courseslughistory_courseid_idx');
            $table->string('aposentadoEm', 191);
        });

        $tomados = [];
        foreach (DB::table('Course')->orderBy('id')->get(['id', 'title']) as $curso) {
            $titulo = is_string($curso->title) ? $curso->title : '';
            $slug = CursoSlug::unico($titulo, $tomados);
            $tomados[] = $slug;

            DB::table('Course')->where('id', $curso->id)->update(['slug' => $slug]);
        }

        Schema::table('Course', function (Blueprint $table) {
            $table->unique('slug', 'course_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('Course', function (Blueprint $table) {
            $table->dropUnique('course_slug_unique');
            $table->dropColumn('slug');
        });

        Schema::dropIfExists('CourseSlugHistory');
    }
};
