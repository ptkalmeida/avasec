<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `config/catalogo_publico.php` é réplica de `featuredCoursesData` (App.tsx).
 *
 * Enquanto as duas telas de catálogo existirem — a Blade em `/cursos` e a do
 * React, alcançável enquanto o SPA viver —, as duas precisam oferecer os mesmos
 * cursos. Uma divergência aqui é pior que no menu: a pessoa veria um curso no
 * resultado da busca e não o encontraria no catálogo, ou o contrário.
 *
 * Confere título, categoria e descrição. A imagem fica de fora: ela é
 * ilustrativa, não é informação, e travar o endereço de uma foto externa
 * quebraria a entrega por um detalhe que não muda o que a pessoa aprende.
 */
final class CatalogoPublicoParityTest extends TestCase
{
    public function test_catalogo_php_espelha_o_array_do_react(): void
    {
        $appPath = dirname(base_path()).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'App.tsx';
        $this->assertFileExists($appPath, 'src/App.tsx não encontrado — a vitrine mudou de lugar?');

        $fonte = (string) file_get_contents($appPath);

        $inicio = strpos($fonte, 'const featuredCoursesData');
        $this->assertNotFalse($inicio, 'featuredCoursesData não encontrado em App.tsx.');
        $bloco = substr($fonte, $inicio);
        $fim = strpos($bloco, '];');
        $this->assertNotFalse($fim, 'Fim de featuredCoursesData não encontrado — o formato mudou?');
        $bloco = substr($bloco, 0, $fim);

        preg_match_all(
            "/title:\s*'((?:[^'\\\\]|\\\\.)*)',\s*\n\s*category:\s*'((?:[^'\\\\]|\\\\.)*)'/",
            $bloco,
            $casados,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($casados, 'Nenhum curso encontrado em featuredCoursesData — o formato mudou?');

        $doReact = array_map(
            static fn (array $m): array => [
                'title' => stripslashes($m[1]),
                'category' => stripslashes($m[2]),
            ],
            $casados,
        );

        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');
        $doPhp = array_map(
            static fn (array $c): array => ['title' => $c['title'], 'category' => $c['category']],
            $vitrine,
        );

        $this->assertSame(
            $doReact,
            $doPhp,
            'config/catalogo_publico.php divergiu de featuredCoursesData (src/App.tsx) — sincronize os dois.',
        );
    }

    public function test_todo_curso_da_vitrine_tem_os_campos_que_a_tela_usa(): void
    {
        /** @var list<array<string, string>> $vitrine */
        $vitrine = config('catalogo_publico');

        foreach ($vitrine as $i => $curso) {
            foreach (['title', 'category', 'instructor', 'image', 'description'] as $campo) {
                $this->assertArrayHasKey($campo, $curso, "Curso {$i} sem o campo {$campo}.");
                $this->assertNotSame('', $curso[$campo], "Curso {$i} com {$campo} vazio.");
            }
        }
    }
}
