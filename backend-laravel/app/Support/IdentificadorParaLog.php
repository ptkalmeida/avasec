<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O que digitaram no campo de login, em forma que pode ir para o registro de auditoria.
 *
 * O log guardava o texto cru, e quem digita a SENHA no campo do CPF por engano — acontece — a
 * deixava gravada na trilha de segurança, que é permanente (ADR 12) e lida por mais gente do que
 * a conta (achado da varredura de segurança de 23/10/2026). Também CPF é dado pessoal.
 *
 * Fica só o que ajuda a investigar sem expor: e-mail em formato de e-mail (é o identificador de
 * gestão e admin); CPF com os dígitos escondidos menos os dois últimos; qualquer outra coisa não
 * é registrada, só o fato de ter vindo fora do formato.
 */
final class IdentificadorParaLog
{
    public static function de(string $texto): string
    {
        $limpo = trim($texto);
        if ($limpo === '') {
            return '(vazio)';
        }

        if (filter_var($limpo, FILTER_VALIDATE_EMAIL) !== false) {
            return mb_strtolower($limpo);
        }

        $digitos = preg_replace('/\D/', '', $limpo) ?? '';
        if (strlen($digitos) === 11 && preg_match('/^[\d.\-\s]+$/', $limpo) === 1) {
            return '***.***.***-'.substr($digitos, -2);
        }

        return '(fora do formato de CPF ou e-mail, '.mb_strlen($limpo).' caracteres)';
    }
}
