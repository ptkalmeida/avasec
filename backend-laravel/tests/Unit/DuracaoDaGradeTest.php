<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DuracaoDaGrade;
use PHPUnit\Framework\TestCase;

/**
 * Espelha `tests/frontend/courseDuration.test.ts`: as duas implementações
 * respondem o mesmo, e este arquivo é o que prende o lado PHP.
 *
 * Os casos de arredondamento abaixo não vêm do Vitest — vêm da conferência
 * cruzada que achou 83 totais onde `number_format` e o `toFixed` do JavaScript
 * discordavam em 0,1 h.
 */
final class DuracaoDaGradeTest extends TestCase
{
    public function test_le_o_formato_que_o_cadastro_usa(): void
    {
        $this->assertSame(15, DuracaoDaGrade::minutos('15 min'));
        $this->assertSame(22, DuracaoDaGrade::minutos('22 min'));
        $this->assertSame(90, DuracaoDaGrade::minutos('90'));
        $this->assertSame(45, DuracaoDaGrade::minutos('45min'));
        $this->assertSame(45, DuracaoDaGrade::minutos('45 minutos'));
    }

    public function test_le_as_variacoes_de_quem_digita_a_mao(): void
    {
        $this->assertSame(60, DuracaoDaGrade::minutos('1h'));
        $this->assertSame(90, DuracaoDaGrade::minutos('1h30'));
        $this->assertSame(120, DuracaoDaGrade::minutos('2 horas'));
        $this->assertSame(90, DuracaoDaGrade::minutos('1 hora 30 min'));
        $this->assertSame(90, DuracaoDaGrade::minutos('1,5h'));
        $this->assertSame(45, DuracaoDaGrade::minutos('  45 MIN '));
    }

    /** Zero somaria em silêncio; um total errado é pior que um que se declara incompleto. */
    public function test_devolve_null_e_nao_zero_para_o_que_nao_reconhece(): void
    {
        foreach (['a combinar', '', '   ', 'quinze', '12:30', 'h30'] as $texto) {
            $this->assertNull(DuracaoDaGrade::minutos($texto), "«{$texto}» deveria ser ilegível.");
        }
        $this->assertNull(DuracaoDaGrade::minutos(null));
    }

    /** `replace(',', '.')` do JavaScript troca só a primeira vírgula. */
    public function test_so_a_primeira_virgula_vira_ponto(): void
    {
        $this->assertNull(DuracaoDaGrade::minutos('1,5,5'));
    }

    public function test_curso_vazio_nao_anuncia_duracao_nenhuma(): void
    {
        $this->assertSame('—', DuracaoDaGrade::texto([]));
        $this->assertSame('—', DuracaoDaGrade::texto([['duracao' => 'a combinar']]));
    }

    public function test_muda_quando_uma_aula_e_acrescentada(): void
    {
        $uma = DuracaoDaGrade::texto([['duracao' => '30 min']]);
        $duas = DuracaoDaGrade::texto([['duracao' => '30 min'], ['duracao' => '45 min']]);

        $this->assertSame('30 min', $uma);
        $this->assertSame('1,3 h', $duas);
    }

    public function test_abaixo_de_uma_hora_fala_em_minutos(): void
    {
        $this->assertSame('20 min', DuracaoDaGrade::texto([['duracao' => '20 min']]));
        $this->assertSame('59 min', DuracaoDaGrade::texto([['duracao' => '59']]));
        $this->assertSame('1,0 h', DuracaoDaGrade::texto([['duracao' => '60']]));
    }

    public function test_declara_quantas_aulas_ficaram_de_fora(): void
    {
        $this->assertSame(
            '1,0 h (1 aula sem duração)',
            DuracaoDaGrade::texto([['duracao' => '1h'], ['duracao' => 'a combinar']]),
        );
        $this->assertSame(
            '1,0 h (2 aulas sem duração)',
            DuracaoDaGrade::texto([['duracao' => '1h'], ['duracao' => ''], ['duracao' => null]]),
        );
    }

    /**
     * O `toFixed(1)` do JavaScript arredonda o valor binário EXATO: 1179 min =
     * 19,6499… h dá "19,6", e `number_format` dava "19,7". E num empate exato
     * (1155 min = 19,25 h) ele escolhe o maior, "19,3", onde o `sprintf` do PHP
     * dava "19,2".
     */
    public function test_arredonda_como_o_javascript(): void
    {
        $this->assertSame('19,6 h', DuracaoDaGrade::texto([['duracao' => '1179']]));
        $this->assertSame('19,3 h', DuracaoDaGrade::texto([['duracao' => '1155']]));
        $this->assertSame('19,9 h', DuracaoDaGrade::texto([['duracao' => '1197']]));
    }
}
