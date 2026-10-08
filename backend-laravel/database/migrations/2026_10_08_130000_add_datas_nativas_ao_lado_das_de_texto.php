<?php

declare(strict_types=1);

use App\Support\DataTexto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data nativa AO LADO de cada coluna de data em texto (Norma C.2.4).
 *
 * Sete colunas deste schema guardam data em `varchar(191)` — herança do backend
 * Node. A norma pede tipo nativo, mas trocar o tipo romperia o contrato das
 * telas que já leem aquele texto pronto. Então nada é trocado: cada uma ganha
 * uma coluna nativa ao lado, preenchida junto na gravação. A coluna antiga e o
 * campo da API continuam exatamente como estão.
 *
 * Mudança **aditiva**, que é o que C.2.5 permite: nenhuma coluna muda de tipo,
 * nenhuma é removida, nenhum dado é reescrito.
 *
 * ## O que isto conserta hoje
 *
 * A trilha de auditoria ordenava por `timestamp`, que é o texto
 * `"HH:MM:SS DD/MM/AAAA"` — ou seja, ordenava pela HORA antes da data: um
 * registro das 23h de ontem vinha depois de um das 08h de hoje. O "mais
 * recente" da tela de segurança estava errado (visto em 24/09/2026). Com
 * `ocorridoEm` a ordenação passa a ser pelo instante.
 *
 * ## Linha que não converte fica NULL
 *
 * Nenhuma data é inventada. O backfill conta e imprime as linhas que não
 * converteram, com exemplo do valor. Na base de 08/10/2026 são zero: as 1.737
 * linhas casaram exatamente um dos cinco formatos conhecidos (ver `DataTexto`).
 *
 * ## Por que `prazoEm` e `vigenciaAte` são DATE, e não DATETIME
 *
 * O plano 11 previa `prazoEm` DATETIME. Os dados dizem outra coisa:
 * `PracticalExercise.dueDate` é `d/m/Y`, sem hora nenhuma, e a validação da
 * rota exige exatamente esse formato (`date_format:d/m/Y`). DATETIME obrigaria
 * a inventar `00:00:00` e, pior, submeteria um vencimento à armadilha de fuso
 * que `Fuso` documenta — meia-noite UTC é 21h do dia ANTERIOR em Brasília, e o
 * dia do prazo mudaria. DATE é o tipo que o dado tem. Desvio do plano
 * sinalizado, não silencioso (AGENTS.md).
 */
return new class extends Migration
{
    /**
     * destino => [tabela, coluna de texto, tipo, coluna de hora separada, indexar]
     *
     * @var list<array{tabela: string, texto: string, nativa: string, tipo: string, hora: ?string, indice: ?string}>
     */
    private const COLUNAS = [
        ['tabela' => 'SecurityLog', 'texto' => 'timestamp', 'nativa' => 'ocorridoEm', 'tipo' => 'datetime', 'hora' => null, 'indice' => 'securitylog_ocorridoem_idx'],
        ['tabela' => 'ChatMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime', 'hora' => null, 'indice' => 'chatmessage_enviadaem_idx'],
        ['tabela' => 'DirectMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime', 'hora' => null, 'indice' => 'directmessage_enviadaem_idx'],
        ['tabela' => 'ForumMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime', 'hora' => null, 'indice' => 'forummessage_enviadaem_idx'],
        ['tabela' => 'Course', 'texto' => 'contractExpirationDate', 'nativa' => 'vigenciaAte', 'tipo' => 'date', 'hora' => null, 'indice' => null],
        ['tabela' => 'PracticalExercise', 'texto' => 'dueDate', 'nativa' => 'prazoEm', 'tipo' => 'date', 'hora' => null, 'indice' => null],
        ['tabela' => 'WebinarEvent', 'texto' => 'date', 'nativa' => 'dataEvento', 'tipo' => 'datetime', 'hora' => 'time', 'indice' => null],
    ];

    public function up(): void
    {
        foreach (self::COLUNAS as $c) {
            if (! Schema::hasTable($c['tabela']) || Schema::hasColumn($c['tabela'], $c['nativa'])) {
                continue;
            }

            Schema::table($c['tabela'], function (Blueprint $t) use ($c): void {
                $coluna = $c['tipo'] === 'date'
                    ? $t->date($c['nativa'])
                    : $t->dateTime($c['nativa']);

                $coluna->nullable()->after($c['texto']);

                if ($c['indice'] !== null) {
                    $coluna->index($c['indice']);
                }
            });

            $this->preencher($c);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::COLUNAS) as $c) {
            if (! Schema::hasTable($c['tabela']) || ! Schema::hasColumn($c['tabela'], $c['nativa'])) {
                continue;
            }

            Schema::table($c['tabela'], function (Blueprint $t) use ($c): void {
                if ($c['indice'] !== null) {
                    $t->dropIndex($c['indice']);
                }
                $t->dropColumn($c['nativa']);
            });
        }
    }

    /**
     * Converte o texto já gravado. Idempotente: só toca linha com a nativa vazia.
     *
     * @param  array{tabela: string, texto: string, nativa: string, tipo: string, hora: ?string, indice: ?string}  $c
     */
    private function preencher(array $c): void
    {
        $campos = array_values(array_filter(['id', $c['texto'], $c['hora']]));
        $convertidas = 0;
        $recusadas = [];

        /*
         * `chunkById`, NUNCA `chunk`. O filtro é `whereNull($nativa)` e o corpo do
         * laço PREENCHE essa mesma coluna: cada linha gravada sai do conjunto. O
         * `chunk` pagina por OFFSET, então o lote seguinte pula tantas linhas
         * quantas acabaram de ser preenchidas — na primeira execução aqui, 1.000
         * das 1.710 linhas do SecurityLog converteram e 710 ficaram para trás, em
         * silêncio. O `chunkById` pagina pela chave, que não se move.
         */
        DB::table($c['tabela'])
            ->whereNull($c['nativa'])
            ->select($campos)
            ->chunkById(500, function ($linhas) use ($c, &$convertidas, &$recusadas): void {
                foreach ($linhas as $linha) {
                    $texto = is_string($linha->{$c['texto']}) ? $linha->{$c['texto']} : null;
                    $hora = $c['hora'] !== null && is_string($linha->{$c['hora']}) ? $linha->{$c['hora']} : null;

                    $data = $c['tipo'] === 'date'
                        ? DataTexto::dia($texto)
                        : DataTexto::instante($texto, $hora);

                    if ($data === null) {
                        if (trim((string) $texto) !== '') {
                            $recusadas[] = (string) $texto;
                        }

                        continue;
                    }

                    DB::table($c['tabela'])->where('id', $linha->id)->update([
                        $c['nativa'] => $data->format($c['tipo'] === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'),
                    ]);
                    $convertidas++;
                }
            });

        $aviso = $recusadas === []
            ? ''
            : sprintf(
                '  %d NAO converteram (ficaram NULL), ex.: %s',
                count($recusadas),
                implode(', ', array_slice(array_unique($recusadas), 0, 3)),
            );

        echo sprintf(
            '  %s.%s: %d convertidas.%s',
            $c['tabela'],
            $c['nativa'],
            $convertidas,
            $aviso,
        ), PHP_EOL;
    }
};
