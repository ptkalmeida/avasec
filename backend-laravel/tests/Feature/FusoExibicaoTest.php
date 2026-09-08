<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Certificate;
use App\Support\Fuso;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fuso de exibição: guardar em UTC, apresentar em BRT.
 *
 * O sintoma era simples e visível: `now()->format('H:i')` marcava 3 horas
 * adiante em toda tela, porque `app.timezone` é UTC e a escola opera em BRT.
 * Uma entrega das 17:08 aparecia como 20:08.
 *
 * O que estes testes protegem não é a conversão — é o INVARIANTE que a correção
 * poderia ter quebrado: UTC continua sendo o relógio de armazenamento. Trocar
 * `app.timezone` teria "consertado" a exibição e, no mesmo gesto, feito todo o
 * histórico já gravado ser lido 3 horas depois do que aconteceu.
 */
final class FusoExibicaoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_armazenamento_continua_em_utc(): void
    {
        // Se algum dia alguém "resolver" o fuso mudando isto, o teste cai — e é
        // a intenção: a troca é retroativa e silenciosa sobre registro acadêmico.
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', CarbonImmutable::now()->getTimezone()->getName());
    }

    public function test_exibicao_usa_o_fuso_da_escola(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.display_timezone'));
        $this->assertSame('America/Sao_Paulo', Fuso::agora()->getTimezone()->getName());
    }

    public function test_o_mesmo_instante_nos_dois_relogios(): void
    {
        // O instante é UM; muda a leitura. Comparar por timestamp prova que a
        // conversão não inventa nem perde tempo — só reapresenta.
        $utc = CarbonImmutable::now();
        $local = Fuso::de($utc);

        $this->assertSame($utc->getTimestamp(), $local->getTimestamp());
        $this->assertNotSame($utc->format('H:i'), $local->format('H:i'));
    }

    public function test_converte_instante_gravado_e_propaga_nulo(): void
    {
        // 2026-09-03 20:00 UTC é 17:00 em São Paulo — o caso real que originou
        // isto: uma revogação feita às 17h aparecia como 20h.
        $gravado = CarbonImmutable::parse('2026-09-03 20:00:17', 'UTC');

        $this->assertSame('03/09/2026 17:00', Fuso::de($gravado)->format('d/m/Y H:i'));
        $this->assertNull(Fuso::local(null));
        $this->assertSame('03/09/2026 17:00', Fuso::local($gravado)?->format('d/m/Y H:i'));
    }

    public function test_data_pura_nao_passa_pela_conversao(): void
    {
        /*
         * A ARMADILHA, prendida aqui de propósito.
         *
         * `Certificate.issueDate` é coluna `DATE`. Data pura não tem instante: o
         * Carbon a materializa à meia-noite, e converter meia-noite UTC para BRT
         * devolve 21h do DIA ANTERIOR. Formatar em `d/m/Y` depois disso trocaria
         * o dia impresso no certificado.
         *
         * Por isso os pontos que formatam `issueDate` NÃO usam `Fuso`. Este
         * teste demonstra o estrago para que ninguém "padronize" aquilo depois.
         */
        $dataPura = CarbonImmutable::parse('2026-09-03 00:00:00', 'UTC');

        $this->assertSame('03/09/2026', $dataPura->format('d/m/Y'));
        $this->assertSame('02/09/2026', Fuso::de($dataPura)->format('d/m/Y'));
    }

    public function test_certificado_serializa_issue_date_no_dia_gravado(): void
    {
        // A prova de que a decisão acima está de fato aplicada: o cast do model
        // formata a data como está na coluna, sem deslocar o dia.
        $cert = new Certificate(['issueDate' => '2026-09-03']);

        $this->assertSame('03/09/2026', $cert->issueDate?->format('d/m/Y'));
    }

    public function test_hora_de_virada_nao_muda_o_dia_do_instante_errado(): void
    {
        /*
         * 2026-09-04 02:00 UTC é ainda 23:00 do dia 03 em São Paulo. É a janela
         * de 3 horas em que formatar em UTC datava certificado, matrícula e
         * solicitação do DIA SEGUINTE ao que a pessoa fez.
         */
        $madrugadaUtc = CarbonImmutable::parse('2026-09-04 02:00:00', 'UTC');

        $this->assertSame('04/09/2026', $madrugadaUtc->format('d/m/Y'));
        $this->assertSame('03/09/2026', Fuso::de($madrugadaUtc)->format('d/m/Y'));
    }
}
