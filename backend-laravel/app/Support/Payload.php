<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalização de payloads aninhados já validados — faz o narrowing em runtime
 * que o Validator garante mas o tipo `array<string, mixed>` não expressa.
 */
final class Payload
{
    /**
     * Normaliza um valor mixed do payload em lista de arrays associativos —
     * itens não-array e chaves não-string são descartados.
     *
     * @return list<array<string, mixed>>
     */
    public static function assocList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $clean = [];
            foreach ($item as $k => $v) {
                if (is_string($k)) {
                    $clean[$k] = $v;
                }
            }
            $out[] = $clean;
        }

        return $out;
    }
}
