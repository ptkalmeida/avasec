<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Senha inicial gerada para uma conta que a coordenação cria ou redefine.
 *
 * Cumpre a `StrongPasswordRule` (mínimo de 8, ao menos uma letra e um dígito) e
 * evita os caracteres que se confundem quando a senha é ditada ou lida de uma
 * tela (`0/O`, `1/l/I`). Tradução de `generateInitialPassword`, do painel React:
 * o sorteio é do servidor, que dispõe de um gerador criptográfico.
 *
 * A senha vai UMA vez para a tela de quem cria a conta, e nada a guarda.
 */
final class SenhaInicial
{
    private const LETRAS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    private const DIGITOS = '23456789';

    public static function gerar(): string
    {
        $alfabeto = self::LETRAS.self::DIGITOS;

        // Ao menos uma letra e um dígito, e depois o resto embaralhado: do
        // contrário a senha teria formato previsível.
        $caracteres = [
            self::LETRAS[random_int(0, strlen(self::LETRAS) - 1)],
            self::DIGITOS[random_int(0, strlen(self::DIGITOS) - 1)],
        ];
        for ($i = 0; $i < 10; $i++) {
            $caracteres[] = $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        // `shuffle` não é criptográfico; a troca de Fisher-Yates abaixo usa `random_int`.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
