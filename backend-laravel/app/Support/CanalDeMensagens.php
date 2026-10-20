<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SettingsService;

/**
 * O canal de mensagens está aberto? — espelha src/utils/canalDeMensagens.ts.
 *
 * A condição é a mesma dos dois lados: a flag do fórum, a das mensagens
 * diretas e `allowDirectMessages`, que é chave **editável pelo administrador**.
 * No React ela já havia sido reunida num lugar só porque o sino do cabeçalho e
 * a aba de mensagens liam condições diferentes e discordavam — um sino
 * piscando levava a uma aba que não existia.
 *
 * Aqui ela existe pelo mesmo motivo, do lado do servidor: o item do menu, a
 * tela e (quando houver) o envio precisam concordar. Com `forum` desligada,
 * que é a configuração de hoje, o canal está fechado para todo mundo.
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
        if (config('features.forum') !== true || config('features.mensagensDiretas') !== true) {
            return false;
        }

        return (app(SettingsService::class)->get()['allowDirectMessages'] ?? null) === true;
    }
}
