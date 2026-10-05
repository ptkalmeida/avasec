<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CursoSlug;
use PHPUnit\Framework\TestCase;

/**
 * O gerador de slug, com os títulos REAIS deste banco.
 *
 * Cada caso aqui é uma armadilha que existe nos dados de hoje ou que um título
 * plausível de amanhã cria — não é exercício de regex.
 */
final class CursoSlugTest extends TestCase
{
    public function test_barra_no_titulo_vira_separador_e_nao_desaparece(): void
    {
        /*
         * `Str::slug` sozinho devolve `uxui-design-...`: ele descarta a barra em
         * vez de tratá-la como fronteira de palavra. O curso existe com este
         * título, então isto seria o endereço real e ilegível dele.
         */
        $this->assertSame(
            'ux-ui-design-interfaces-de-alta-performance',
            CursoSlug::radical('UX/UI Design: Interfaces de Alta Performance')
        );
    }

    public function test_acento_e_pontuacao_saem_do_endereco(): void
    {
        $this->assertSame(
            'metodologias-ageis-e-kanban-na-gestao-escolar-e-ti',
            CursoSlug::radical('Metodologias Ágeis e Kanban na Gestão Escolar e TI')
        );
        // `&` é separador, não a palavra "e": traduzir símbolo seria inventar
        // texto que ninguém escreveu no título.
        $this->assertSame('acao-reacao-2026-nivel-avancado', CursoSlug::radical('Ação & Reação (2026) — nível avançado'));
    }

    public function test_titulo_com_forma_de_id_nao_gera_slug_com_forma_de_id(): void
    {
        /*
         * A armadilha que motivou a guarda: o roteador aceita id (link antigo) e
         * slug. Um curso chamado "Course 1" geraria o slug `course-1`, que é o id
         * de OUTRO curso — e o link abriria o curso errado, sem erro na tela.
         */
        $slug = CursoSlug::radical('Course 1');

        $this->assertFalse(CursoSlug::ehFormaDeId($slug), "O slug '{$slug}' colide com um id de curso.");
        $this->assertSame('curso-course-1', $slug);

        $this->assertTrue(CursoSlug::ehFormaDeId('course-1'));
        $this->assertTrue(CursoSlug::ehFormaDeId('course-1787928131660'));
        $this->assertFalse(CursoSlug::ehFormaDeId('curso-de-fotografia'));
    }

    public function test_titulo_sem_nada_legivel_cai_no_radical_e_nao_em_vazio(): void
    {
        // Slug vazio some do caminho: `/aluno/curso//aula/x` não é endereço.
        foreach (['', '   ', '---', '!!! ??? ...', '///'] as $titulo) {
            $this->assertSame('curso', CursoSlug::radical($titulo), "Título {$titulo} gerou slug vazio.");
        }
    }

    public function test_titulo_muito_longo_e_cortado_em_palavra_inteira(): void
    {
        $longo = str_repeat('planejamento estrategico ', 20);
        $slug = CursoSlug::radical($longo);

        $this->assertLessThanOrEqual(150, mb_strlen($slug));
        // Não termina no meio de uma palavra nem em hífen solto.
        $this->assertStringEndsWith('estrategico', $slug);
        $this->assertStringNotContainsString('--', $slug);
    }

    public function test_titulo_repetido_recebe_sufixo_legivel(): void
    {
        $tomados = ['curso-de-teste'];

        $this->assertSame('curso-de-teste-2', CursoSlug::unico('Curso de Teste', $tomados));
        $this->assertSame(
            'curso-de-teste-3',
            CursoSlug::unico('Curso de Teste', ['curso-de-teste', 'curso-de-teste-2'])
        );
        // Livre continua livre: não se acrescenta sufixo por precaução.
        $this->assertSame('curso-de-fotografia', CursoSlug::unico('Curso de fotografia', $tomados));
    }

    public function test_slug_aposentado_tambem_ocupa(): void
    {
        /*
         * Um slug aposentado ainda resolve para o curso antigo. Se um curso novo
         * pudesse reaproveitá-lo, o link salvo de um curso passaria a abrir
         * OUTRO — pior que o link morto que esta mudança veio evitar.
         */
        $this->assertSame('curso-de-teste-2', CursoSlug::unico('Curso de Teste', ['curso-de-teste']));
    }
}
