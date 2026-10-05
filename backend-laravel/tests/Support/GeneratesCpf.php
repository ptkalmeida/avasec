<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Geração de CPF sintético VÁLIDO para fixtures (ADR 11). O cadastro de aluno
 * exige CPF com dígitos verificadores corretos e único no banco, então os
 * testes precisam produzir CPFs de verdade — não strings arbitrárias.
 */
trait GeneratesCpf
{
    /** CPF com dígitos verificadores corretos e ainda não usado no banco. */
    protected function makeCpf(): string
    {
        do {
            $base = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            if (preg_match('/^(\d)\1{8}$/', $base) === 1) {
                continue;
            }
            $cpf = $base.$this->cpfCheckDigits($base);
        } while (DB::table('User')->where('cpf', $cpf)->exists());

        return $cpf;
    }

    /** Os dois dígitos verificadores de uma base de 9 dígitos. */
    private function cpfCheckDigits(string $base): string
    {
        $digits = $base;
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digits[$i] * (($position + 1) - $i);
            }
            $digits .= (string) (((10 * $sum) % 11) % 10);
        }

        return substr($digits, 9);
    }
}
