<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data ordenável para as tentativas de avaliação.
 *
 * `submittedAt` é string de EXIBIÇÃO ('03/09/2026 às 16:23'), e ordenar por ela
 * é ordenar alfabeticamente: '01/12/2026' vem antes de '03/09/2026'. Enquanto
 * havia uma tentativa por aluno+quiz isso não aparecia — a lista tinha uma linha
 * só. Para mostrar o histórico é preciso saber qual é a vigente, e é isso que
 * falta aqui.
 *
 * A coluna nova não substitui `submittedAt`: o contrato da API já entrega aquela
 * string pronta para a tela, e reescrever o formato romperia todo consumidor.
 * `enviadoEm` é o eixo de ordenação; `submittedAt` continua sendo o que se lê.
 *
 * Nullable de propósito: linha cuja string não seja reconhecível NÃO recebe data
 * inventada. Sem data, o consumidor a trata como a mais antiga — o que é
 * honesto — em vez de fingir precisão que não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('QuizSubmission', function (Blueprint $table): void {
            $table->dateTime('enviadoEm')->nullable()->after('submittedAt')
                ->index('quizsubmission_enviadoem_idx');
        });

        foreach (DB::table('QuizSubmission')->select('id', 'submittedAt')->get() as $linha) {
            $data = $this->interpretar(is_string($linha->submittedAt) ? $linha->submittedAt : '');
            if ($data !== null) {
                DB::table('QuizSubmission')->where('id', $linha->id)
                    ->update(['enviadoEm' => $data->format('Y-m-d H:i:s')]);
            }
        }

        /*
         * Devolve ao ar as tentativas que a própria regra superada inativou.
         *
         * Até aqui, responder de novo inativava a tentativa anterior (ADR 12: o
         * dado ficou, apenas invisível). Agora que há como ordenar, o histórico
         * inteiro volta a ser exibível — a linha não precisava mais estar fora
         * das listagens.
         *
         * O filtro por `inativadoPor` e `motivoInativacao` nulos é o que separa
         * a inativação AUTOMÁTICA (que nunca preenche autoria) de uma decisão
         * humana. Se algum dia existir rota para um gestor retirar uma nota do
         * ar, aquela linha registra quem e por quê, e esta migration não a toca.
         */
        DB::table('QuizSubmission')
            ->whereNotNull('inativadoEm')
            ->whereNull('inativadoPor')
            ->whereNull('motivoInativacao')
            ->update(['inativadoEm' => null]);
    }

    public function down(): void
    {
        Schema::table('QuizSubmission', function (Blueprint $table): void {
            $table->dropIndex('quizsubmission_enviadoem_idx');
            $table->dropColumn('enviadoEm');
        });
    }

    /**
     * Interpreta as formas que o projeto já gravou em `submittedAt`, ou null.
     *
     * Formas conhecidas: 'd/m/Y às H:i' (submitQuiz), 'd/m/Y H:i:s' e 'd/m/Y'.
     * Nada de `strtotime`: ele lê '03/09/2026' como o padrão americano e
     * transformaria 3 de setembro em 9 de março.
     */
    private function interpretar(string $valor): ?CarbonImmutable
    {
        $limpo = trim(str_replace(' às ', ' ', $valor));
        if ($limpo === '') {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $formato) {
            try {
                $data = CarbonImmutable::createFromFormat($formato, $limpo);
            } catch (Throwable) {
                continue;
            }
            // createFromFormat aceita '32/13/2026' e rola para o mês seguinte;
            // reformatar e comparar é o que rejeita data impossível.
            if ($data !== false && $data->format($formato) === $limpo) {
                return $formato === 'd/m/Y' ? $data->startOfDay() : $data;
            }
        }

        return null;
    }
};
