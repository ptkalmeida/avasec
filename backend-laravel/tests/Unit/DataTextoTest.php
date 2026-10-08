<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DataTexto;
use Tests\TestCase;

/**
 * O tradutor das datas que este banco guardou como texto.
 *
 * Cada caso abaixo é um valor que **existe nos dados de hoje** (varredura de
 * 08/10/2026, 1.737 linhas em sete colunas) ou uma armadilha que já custou bug
 * em outro ponto do projeto. Não é exercício de parser.
 *
 * Estende a TestCase do Laravel, e não a do PHPUnit, porque a conversão de fuso
 * depende de `config('app.display_timezone')` — sem o container, a classe leria
 * UTC e os testes passariam medindo a coisa errada.
 */
final class DataTextoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prende o fuso de exibição: se alguém trocar a configuração, o que quebra
        // é a aplicação, não este teste — e o teste tem de continuar dizendo a
        // verdade sobre a conversão.
        config(['app.display_timezone' => 'America/Sao_Paulo']);
    }

    /**
     * O formato de 1.710 das 1.737 linhas: a trilha de auditoria.
     *
     * Hora ANTES da data é exatamente o motivo de a ordenação por texto estar
     * errada — "23:00 07/10" ordena depois de "08:00 08/10".
     */
    public function test_hora_antes_da_data_do_log_de_seguranca(): void
    {
        $i = DataTexto::instante('07:04:16 23/07/2026');

        $this->assertNotNull($i);
        // 07:04 em Brasília são 10:04 em UTC, que é como se guarda.
        $this->assertSame('2026-07-23 10:04:16', $i->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $i->getTimezone()->getName());
    }

    public function test_iso_com_z_e_iso_com_deslocamento_valem_o_mesmo_instante(): void
    {
        $comZ = DataTexto::instante('2026-07-23T15:04:16.386Z');
        $comOffset = DataTexto::instante('2026-07-23T12:04:16-03:00');

        $this->assertNotNull($comZ);
        $this->assertNotNull($comOffset);
        $this->assertSame('2026-07-23 15:04:16', $comZ->format('Y-m-d H:i:s'));
        $this->assertSame($comZ->format('Y-m-d H:i:s'), $comOffset->format('Y-m-d H:i:s'));
    }

    /**
     * O fórum tem DUAS formas gravadas: com vírgula (semeador antigo) e sem
     * vírgula (`LearningService::createForumMessage`, hoje). Se só uma fosse
     * aceita, metade das mensagens ficaria sem data nativa.
     */
    public function test_forum_aceita_com_e_sem_virgula(): void
    {
        $comVirgula = DataTexto::instante('15/06/2026, 14:32');
        $semVirgula = DataTexto::instante('15/06/2026 14:32');

        $this->assertNotNull($comVirgula);
        $this->assertNotNull($semVirgula);
        $this->assertSame('2026-06-15 17:32:00', $comVirgula->format('Y-m-d H:i:s'));
        $this->assertSame($comVirgula->format('Y-m-d H:i:s'), $semVirgula->format('Y-m-d H:i:s'));
    }

    /**
     * Sem o `!` no formato, `createFromFormat('d/m/Y H:i', ...)` herda os
     * SEGUNDOS do relógio de quem rodou: duas linhas do mesmo minuto ficariam
     * com instantes diferentes, e o resultado do backfill dependeria da hora em
     * que ele foi executado.
     */
    public function test_segundos_nao_vazam_do_relogio_atual(): void
    {
        $i = DataTexto::instante('15/06/2026 14:32');

        $this->assertNotNull($i);
        $this->assertSame('00', $i->format('s'));
    }

    public function test_dia_e_hora_em_colunas_separadas_viram_um_instante(): void
    {
        // WebinarEvent guarda '15/06/2026' em `date` e '19:00' em `time`.
        $i = DataTexto::instante('15/06/2026', '19:00');

        $this->assertNotNull($i);
        // 19h em Brasília = 22h UTC.
        $this->assertSame('2026-06-15 22:00:00', $i->format('Y-m-d H:i:s'));
    }

    public function test_hora_separada_invalida_nao_estraga_o_dia(): void
    {
        $i = DataTexto::instante('15/06/2026', '99:99');

        $this->assertNotNull($i, 'Hora inválida não pode derrubar uma data que é válida.');
        $this->assertSame('2026-06-15', $i->setTimezone('America/Sao_Paulo')->format('Y-m-d'));
    }

    /**
     * A armadilha que `Fuso` documenta: data pura NÃO passa por fuso. Meia-noite
     * UTC é 21h do dia ANTERIOR em Brasília — e o dia do vencimento mudaria.
     */
    public function test_data_pura_nao_anda_um_dia_para_tras(): void
    {
        $this->assertSame('2027-12-31', DataTexto::dia('2027-12-31')?->format('Y-m-d'));
        $this->assertSame('2026-07-10', DataTexto::dia('10/07/2026')?->format('Y-m-d'));
    }

    /**
     * `strtotime('03/09/2026')` devolve 9 de MARÇO: lê o padrão americano. É o
     * motivo de esta classe interpretar com formato fixo.
     */
    public function test_dia_barra_mes_nao_e_lido_como_mes_barra_dia(): void
    {
        $this->assertSame('2026-09-03', DataTexto::dia('03/09/2026')?->format('Y-m-d'));
    }

    /**
     * `createFromFormat` aceita '32/13/2026' e rola para o mês seguinte. Data
     * impossível tem de virar NULL, não uma data plausível e falsa.
     */
    public function test_data_impossivel_nao_vira_data_plausivel(): void
    {
        $this->assertNull(DataTexto::instante('32/13/2026'));
        $this->assertNull(DataTexto::dia('32/13/2026'));
        $this->assertNull(DataTexto::dia('2026-02-30'));
        $this->assertNull(DataTexto::instante('25:00:00 23/07/2026'));
    }

    public function test_vazio_e_lixo_viram_nulo_sem_estourar(): void
    {
        foreach ([null, '', '   ', 'amanhã', 'a combinar', '2026', 'Dez/2026'] as $valor) {
            $this->assertNull(DataTexto::instante($valor), "instante('".var_export($valor, true)."')");
            $this->assertNull(DataTexto::dia($valor), "dia('".var_export($valor, true)."')");
        }
    }

    /** ISO num campo de dia: o dia é aproveitado, a hora é descartada. */
    public function test_dia_aproveita_o_dia_de_um_valor_com_hora(): void
    {
        $this->assertSame('2026-06-15', DataTexto::dia('15/06/2026 14:32')?->format('Y-m-d'));
    }
}
