<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converte LiveSession.scheduledAt de texto livre para data ISO local.
 *
 * O campo guardava o que o gestor digitasse: "Hoje, às 19:30", "Amanhã, às 18:00",
 * "Próxima Segunda, às 20:00". Sem data real não há como ordenar nem filtrar, e era
 * isso que impedia a agenda dos próximos 30 dias na aba Calendário.
 *
 * Por que converter em vez de deixar o legado quieto: a validação da API passou a
 * exigir `Y-m-d\TH:i`, e o PUT /api/courses/{id} reenvia TODAS as sessões do curso.
 * Uma linha em formato antigo faria qualquer edição daquele curso falhar com 400 —
 * o gestor abriria o curso para mudar o título de uma aula e não conseguiria salvar.
 *
 * A conversão é o melhor esforço possível: "Hoje" e "Amanhã" são relativos a QUANDO
 * foram escritos, informação que não existe mais (a tabela não tem createdAt). Ficam
 * ancorados na data desta migração, o que é uma aproximação e está registrado aqui
 * de propósito. São dados de demonstração; nenhuma turma real depende deles.
 */
return new class extends Migration
{
    /** Dias da semana como aparecem no texto antigo, em minúsculas sem acento. */
    private const DIAS = [
        'domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3,
        'quinta' => 4, 'sexta' => 5, 'sabado' => 6,
    ];

    public function up(): void
    {
        $base = CarbonImmutable::now()->startOfDay();

        foreach (DB::table('LiveSession')->get(['id', 'scheduledAt']) as $sessao) {
            $atual = is_string($sessao->scheduledAt) ? $sessao->scheduledAt : '';

            // Já convertida (ou gravada pelo formulário novo): não toca.
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $atual) === 1) {
                continue;
            }

            $convertida = $this->converter($atual, $base);
            DB::table('LiveSession')->where('id', $sessao->id)->update(['scheduledAt' => $convertida]);
        }
    }

    /**
     * A volta não restaura o texto original — ele não é recuperável a partir da data.
     * Deixa um valor legível equivalente, para o rollback não gravar ISO num campo
     * que o código antigo trataria como frase.
     */
    public function down(): void
    {
        foreach (DB::table('LiveSession')->get(['id', 'scheduledAt']) as $sessao) {
            $data = CarbonImmutable::createFromFormat('Y-m-d\TH:i', substr((string) $sessao->scheduledAt, 0, 16));
            if ($data === false) {
                continue;
            }
            DB::table('LiveSession')->where('id', $sessao->id)
                ->update(['scheduledAt' => $data->format('d/m/Y').', às '.$data->format('H:i')]);
        }
    }

    /** Traduz o texto antigo para ISO local, ancorando o relativo em $base. */
    private function converter(string $texto, CarbonImmutable $base): string
    {
        $normalizado = $this->semAcento(mb_strtolower(trim($texto)));

        // Hora, quando existir no texto ("às 19:30" / "19h30" / "19:30").
        $hora = 20;
        $minuto = 0;
        if (preg_match('/(\d{1,2})\s*[:h]\s*(\d{2})?/', $normalizado, $m) === 1) {
            $hora = min(23, (int) $m[1]);
            $minuto = isset($m[2]) && $m[2] !== '' ? min(59, (int) $m[2]) : 0;
        }

        $dia = $base;

        if (str_contains($normalizado, 'amanha')) {
            $dia = $base->addDay();
        } elseif (str_contains($normalizado, 'hoje')) {
            $dia = $base;
        } else {
            foreach (self::DIAS as $nome => $numero) {
                if (str_contains($normalizado, $nome)) {
                    // "Próxima Segunda" — o próximo dia dessa semana à frente de hoje.
                    $dia = $base->next($numero);
                    break;
                }
            }
        }

        return $dia->setTime($hora, $minuto)->format('Y-m-d\TH:i');
    }

    private function semAcento(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);
    }
};
