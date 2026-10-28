<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DataTexto;
use App\Support\Fuso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `TentativaAvaliacao.enviado_em` tem de exibir a MESMA hora que `enviado_em_texto` diz.
 *
 * As duas colunas descrevem o mesmo evento em formatos diferentes: `enviado_em_texto`
 * é a string que a tela lê, em hora de Brasília; `enviado_em` é o instante em UTC
 * que ordena a lista. Se converter `enviado_em` para o fuso de exibição não
 * devolver a hora do texto, uma das duas está mentindo.
 *
 * Foi o que aconteceu entre 03/09 e 15/10/2026: o backfill leu a hora de parede
 * de Brasília como se fosse UTC e gravou o instante três horas antes do evento.
 */
final class FusoDoEnviadoEmTest extends TestCase
{
    public function test_enviadoem_exibe_a_mesma_hora_que_o_texto_diz(): void
    {
        $linhas = DB::table('tentativa_avaliacao')->whereNotNull('enviado_em')
            ->select('id', 'enviado_em_texto', 'enviado_em')->get();

        if ($linhas->isEmpty()) {
            $this->markTestSkipped('Nenhuma submissao com enviado_em no banco.');
        }

        foreach ($linhas as $linha) {
            $texto = trim(str_replace(' às ', ' ', (string) $linha->enviado_em_texto));
            $esperado = DataTexto::instante($texto);

            if ($esperado === null) {
                // Texto irreconhecivel: a coluna pode ser nula, e a migration
                // nao inventa data. Nada a comparar.
                continue;
            }

            $exibido = CarbonImmutable::parse((string) $linha->enviado_em, 'UTC')
                ->setTimezone(Fuso::exibicao())
                ->format('d/m/Y H:i');

            $this->assertSame(
                $esperado->setTimezone(Fuso::exibicao())->format('d/m/Y H:i'),
                $exibido,
                "A submissao {$linha->id} grava um instante que nao corresponde ao texto "
                ."\"{$linha->enviado_em_texto}\". Provavel leitura de hora de Brasilia como UTC.",
            );
        }
    }

    public function test_nenhuma_linha_guarda_a_hora_de_parede_como_se_fosse_utc(): void
    {
        /*
         * O sintoma do defeito, isolado: uma linha em que o instante gravado,
         * lido como UTC, exibe a mesma hora do texto. Brasilia e UTC-3 o ano
         * inteiro desde 2019, entao isso e impossivel numa conversao correta —
         * e e exatamente o que o backfill de 03/09 produzia.
         */
        $suspeitas = [];

        foreach (DB::table('tentativa_avaliacao')->whereNotNull('enviado_em')
            ->select('id', 'enviado_em_texto', 'enviado_em')->get() as $linha) {
            $texto = trim(str_replace(' às ', ' ', (string) $linha->enviado_em_texto));
            $correto = DataTexto::instante($texto);

            if ($correto === null) {
                continue;
            }

            $paredeGravada = substr((string) $linha->enviado_em, 0, 16);
            $paredeDoTexto = $correto->setTimezone(Fuso::exibicao())->format('Y-m-d H:i');

            if ($paredeGravada === $paredeDoTexto) {
                $suspeitas[] = $linha->id;
            }
        }

        $this->assertSame([], $suspeitas, 'Linhas gravadas com hora de parede no lugar de UTC.');
    }
}
