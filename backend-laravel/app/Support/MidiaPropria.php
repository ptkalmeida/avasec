<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Leitura tipada de config/midia.php (ADR 023). Um lugar só para o interruptor, para
 * que "a mídia própria está ligada?" não seja respondido de duas maneiras.
 */
final class MidiaPropria
{
    public static function ativa(): bool
    {
        return config('midia.driver') === 'minio';
    }

    public static function disco(): string
    {
        return self::texto('midia.disco', 'midia');
    }

    public static function urlPublica(): string
    {
        return self::texto('midia.url_publica', '');
    }

    public static function ffmpeg(): string
    {
        return self::texto('midia.ffmpeg', 'ffmpeg');
    }

    public static function fila(): string
    {
        return self::texto('midia.fila', 'midia');
    }

    public static function timeout(): int
    {
        return self::inteiro('midia.timeout', 3600);
    }

    public static function geraHls(): bool
    {
        return config('midia.hls') === true;
    }

    /** @return list<int> */
    public static function alturas(): array
    {
        $configuradas = config('midia.alturas');
        $alturas = [];
        foreach (is_array($configuradas) ? $configuradas : [] as $altura) {
            if (is_numeric($altura) && (int) $altura > 0) {
                $alturas[] = (int) $altura;
            }
        }

        return $alturas !== [] ? $alturas : [720];
    }

    private static function texto(string $chave, string $padrao): string
    {
        $valor = config($chave);

        return is_string($valor) ? $valor : $padrao;
    }

    private static function inteiro(string $chave, int $padrao): int
    {
        $valor = config($chave);

        return is_numeric($valor) ? (int) $valor : $padrao;
    }
}
