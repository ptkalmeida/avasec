<?php

declare(strict_types=1);

use App\Support\DataTexto;
use App\Support\Fuso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige o fuso das linhas preenchidas pelo backfill de 03/09/2026.
 *
 * ## O defeito
 *
 * `2026_09_03_140000_add_enviadoEm_to_QuizSubmission` leu `submittedAt` com
 * `CarbonImmutable::createFromFormat($formato, $limpo)` — sem fuso. Sem o
 * terceiro argumento, o Carbon usa o fuso da aplicação, que é **UTC**
 * (`config/app.php`). Mas aquelas strings foram escritas por `Fuso::agora()` e
 * são **hora de parede de Brasília**. Lê-las como UTC grava um instante três
 * horas antes do que aconteceu.
 *
 * A regra da casa está em `App\Support\Fuso`: UTC é o relógio de armazenamento,
 * e a conversão acontece na borda. `App\Support\DataTexto`, escrito depois, já
 * faz o certo — e documenta no próprio cabeçalho que esta coluna ficou errada
 * de propósito, por ser mudança de contrato de API que exigia decisão humana.
 * A decisão veio em 15/10/2026.
 *
 * ## Por que não é a tabela inteira
 *
 * Medido antes de escrever: das 4 linhas com `enviadoEm`, **2 estão erradas**
 * (as de 03/09, gravadas pelo backfill) e **2 estão certas** (08/09 e 24/09,
 * gravadas pelo caminho normal do app, que converte corretamente). Corrigir
 * tudo quebraria as que já estavam boas.
 *
 * | texto exibido       | coluna antes        | desvio |
 * |---------------------|---------------------|--------|
 * | 03/09 16:23         | 2026-09-03 16:23    | 0h     |
 * | 03/09 20:08         | 2026-09-03 20:08    | 0h     |
 * | 08/09 13:24         | 2026-09-08 16:24    | +3h    |
 * | 24/09 11:34         | 2026-09-24 14:34    | +3h    |
 *
 * O critério de seleção é o próprio sintoma, não a data: a linha é candidata
 * quando o instante gravado, lido como UTC, exibe a MESMA hora de parede que o
 * texto. Isso é impossível numa conversão correta (Brasília é UTC-3 o ano
 * inteiro desde 2019) e identifica exatamente o que o backfill escreveu.
 *
 * ## Nada é apagado (ADR 12)
 *
 * `submittedAt` — a string que a tela lê — não é tocada. Só a coluna de
 * ordenação muda, e para o valor correto.
 */
return new class extends Migration
{
    public function up(): void
    {
        $corrigidas = 0;
        $jaCertas = 0;
        $semTexto = 0;

        foreach (DB::table('QuizSubmission')->whereNotNull('enviadoEm')
            ->select('id', 'submittedAt', 'enviadoEm')->get() as $linha) {
            // O backfill tirava ' às ' antes de interpretar; o texto gravado
            // continua com ele ("03/09/2026 às 16:23").
            $texto = trim(str_replace(' às ', ' ', (string) $linha->submittedAt));
            $correto = DataTexto::instante($texto);

            if ($correto === null) {
                $semTexto++;

                continue;
            }

            $gravado = (string) $linha->enviadoEm;
            // Hora de parede que o valor gravado exibe quando lido como UTC.
            $paredeGravada = substr($gravado, 0, 16);
            $paredeDoTexto = $correto->setTimezone(Fuso::exibicao())->format('Y-m-d H:i');

            if ($paredeGravada !== $paredeDoTexto) {
                $jaCertas++;

                continue;
            }

            DB::table('QuizSubmission')->where('id', $linha->id)
                ->update(['enviadoEm' => $correto->format('Y-m-d H:i:s')]);
            $corrigidas++;
        }

        echo sprintf(
            "enviadoEm: %d corrigida(s), %d ja correta(s), %d sem texto reconhecivel.\n",
            $corrigidas,
            $jaCertas,
            $semTexto,
        );
    }

    /**
     * Sem volta automática, de propósito.
     *
     * Desfazer significaria regravar o instante ERRADO. A Norma C.2.6 já proíbe
     * `migrate:rollback` como procedimento, e aqui a reversão não teria nem
     * finalidade: o valor anterior não era um estado válido, era um defeito.
     */
    public function down(): void
    {
        echo "Nada a desfazer: a correcao substitui um valor errado por um correto.\n";
    }
};
