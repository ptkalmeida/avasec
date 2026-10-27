<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SettingsService;

/**
 * O canal de mensagens entre aluno e gestor está aberto?
 *
 * ## Por que o fórum NÃO entra nesta conta
 *
 * No React a condição era `forum && mensagensDiretas && allowDirectMessages`,
 * e isso misturava duas funcionalidades. `forum` é o MURAL DO CURSO
 * (`/api/forum`: mensagens públicas da turma, com curtida e moderação). A
 * conversa com o gestor é outra coisa, e vive em `/api/mensagens-diretas`.
 *
 * O preço dessa mistura foi concreto: de 19 a 20/10 o aluno não tinha como
 * pedir ajuda, porque a tela de ler mensagens dependia de uma flag que estava
 * desligada por causa do mural. E havia resposta do gestor no banco que o aluno
 * nunca pôde ler.
 *
 * Aqui a conta é a que descreve a função: a flag das mensagens diretas, mais a
 * permissão que o administrador controla. Decisão do responsável, 21/10.
 *
 * O React continua com a conta antiga em `src/utils/canalDeMensagens.ts` — ele
 * sai na fase M7, e mudar as duas ao mesmo tempo mexeria numa tela que está
 * sendo substituída.
 */
final class CanalDeMensagens
{
    public static function abertoParaInstrutor(): bool
    {
        return self::aberto();
    }

    public static function abertoParaAluno(): bool
    {
        return self::aberto();
    }

    private static function aberto(): bool
    {
        if (config('features.mensagensDiretas') !== true) {
            return false;
        }

        return (app(SettingsService::class)->get()['allowDirectMessages'] ?? null) === true;
    }
}
