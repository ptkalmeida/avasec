<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O que faz uma questão de prova ser uma questão que se pode responder —
 * porte de `problemaNaQuestao` (`AvaliacoesManagePanel.tsx`).
 *
 * ## Por que isto está no servidor
 *
 * Estas regras só existiam no NAVEGADOR. `POST /api/avaliacoes` conferia o formato
 * (enunciado preenchido, ao menos duas alternativas) e mais nada: o gabarito
 * `indice_opcao_correta` aceitava qualquer inteiro. Uma prova com 2 alternativas
 * e gabarito 9 era gravada sem reclamação, e **ninguém conseguia acertá-la**.
 * Como a prova é exigida para o certificado, ela bloquearia a turma inteira.
 *
 * Duas portas usam esta classe: a tela do instrutor, para dizer o problema da
 * questão N em português, e `LearningService::createQuiz`, que é o invariante —
 * a API não pode gravar o que a tela se recusa a gravar.
 */
final class QuestaoDeProva
{
    public const MIN_ALTERNATIVAS = 2;

    public const MAX_ALTERNATIVAS = 6;

    /** @var list<string> */
    public const LETRAS = ['A', 'B', 'C', 'D', 'E', 'F'];

    /**
     * O primeiro problema da questão, ou null quando ela está em ordem.
     *
     * `$alternativas` é a lista COMO FOI DIGITADA, com as vazias do fim ainda
     * lá: alternativa em branco no MEIO deslocaria o gabarito ao salvar, porque
     * as vazias são descartadas — e isso só se vê olhando a lista inteira.
     *
     * @param  list<string>  $alternativas
     */
    public static function problema(string $enunciado, array $alternativas, int $correta): ?string
    {
        if (trim($enunciado) === '') {
            return 'Escreva o enunciado da questão.';
        }

        $preenchidas = array_values(array_filter(
            array_map(static fn (string $o): string => trim($o), $alternativas),
            static fn (string $o): bool => $o !== '',
        ));

        if (count($preenchidas) < self::MIN_ALTERNATIVAS) {
            return 'Preencha ao menos '.self::MIN_ALTERNATIVAS.' alternativas.';
        }

        if (count($alternativas) > self::MAX_ALTERNATIVAS) {
            return 'No máximo '.self::MAX_ALTERNATIVAS.' alternativas.';
        }

        foreach ($alternativas as $i => $opcao) {
            if (trim($opcao) === '' && $i < count($preenchidas)) {
                return 'A alternativa '.(self::LETRAS[$i] ?? (string) ($i + 1)).' está em branco entre outras preenchidas.';
            }
        }

        // A alternativa certa tem de EXISTIR e ter texto: o gabarito que aponta
        // para o vazio é uma questão que o aluno não consegue acertar.
        if ($correta < 0 || $correta >= count($alternativas) || trim($alternativas[$correta]) === '') {
            return $correta >= 0 && $correta < count($alternativas)
                ? 'A alternativa marcada como correta está em branco.'
                : 'A alternativa marcada como correta não existe.';
        }

        $normalizadas = array_map(static fn (string $o): string => mb_strtolower($o), $preenchidas);
        if (count(array_unique($normalizadas)) !== count($normalizadas)) {
            return 'Há alternativas repetidas — o aluno teria duas respostas certas.';
        }

        return null;
    }
}
