<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Cpf;
use Illuminate\Console\Command;

/**
 * Atribui CPF às contas de aluno que nasceram sem ele.
 *
 * Por que existe: o CPF é o identificador de login do aluno (ADR 11) — a aba
 * "Aluno" da tela de acesso pede CPF e nada mais. As contas criadas antes dessa
 * regra ficaram com `cpf = NULL` e, portanto, sem NENHUM caminho de entrada.
 *
 * Por que é comando e não migration: inventar documento de pessoa é aceitável em
 * base de teste e inaceitável em produção. Migration roda sozinha em todo
 * ambiente; comando é alguém decidindo, num ambiente específico, e vendo o que
 * saiu. `--dry-run` mostra antes.
 *
 * O CPF gerado é fictício com dígitos verificadores válidos (o login precisa
 * aceitá-lo), derivado do id da conta para ser estável entre execuções — rodar
 * duas vezes não troca o CPF de ninguém, e contas que já têm CPF são ignoradas.
 *
 * Uso:
 *   php artisan avasec:backfill-student-cpf --dry-run
 *   php artisan avasec:backfill-student-cpf
 */
final class BackfillStudentCpf extends Command
{
    protected $signature = 'avasec:backfill-student-cpf
        {--dry-run : Apenas lista as contas sem CPF e o CPF que seria atribuído}';

    protected $description = 'Atribui CPF fictício válido às contas de aluno sem CPF (ambiente de teste)';

    public function handle(): int
    {
        $semCpf = User::query()
            ->where('role', 'student')
            ->where(function ($q): void {
                $q->whereNull('cpf')->orWhere('cpf', '');
            })
            ->orderBy('name')
            ->get();

        if ($semCpf->isEmpty()) {
            $this->info('Toda conta de aluno já tem CPF. Nada a fazer.');

            return self::SUCCESS;
        }

        if (app()->environment('production')) {
            $this->error('Ambiente de produção: este comando inventa documento e não roda aqui.');

            return self::FAILURE;
        }

        $seco = $this->option('dry-run') === true;
        $this->newLine();
        $this->warn($semCpf->count().' conta(s) de aluno sem CPF — hoje não conseguem passar pela tela de login:');

        $linhas = [];
        foreach ($semCpf as $user) {
            $cpf = $this->cpfFicticioPara((string) $user->id);

            // Colisão é improvável, mas o índice é único: pular é melhor que abortar.
            if (User::query()->where('cpf', $cpf)->exists()) {
                $this->error("  - {$user->name}: CPF gerado já em uso, conta ignorada.");

                continue;
            }

            if (! $seco) {
                $user->cpf = $cpf;
                $user->save();
            }

            $linhas[] = [$user->name, $user->email, Cpf::format($cpf)];
        }

        $this->newLine();
        $this->table(['Aluno', 'E-mail', 'CPF de login'], $linhas);

        if ($seco) {
            $this->info('--dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('CPFs atribuídos. A senha de cada conta NÃO foi alterada — para definir uma,');
        $this->info('use "Redefinir Senha" no menu de ações do aluno, na área de gestão.');

        return self::SUCCESS;
    }

    /**
     * Gera 9 dígitos a partir de um hash do id (estável, sem aleatoriedade) e
     * completa com os dois dígitos verificadores reais.
     */
    private function cpfFicticioPara(string $id): string
    {
        $base = '';
        foreach (str_split(substr(hash('sha256', 'avasec-cpf-ficticio:'.$id), 0, 18), 2) as $par) {
            $base .= (string) (hexdec($par) % 10);
        }

        // Dígito repetido 11 vezes é recusado por Cpf::isValid; empurra o primeiro.
        if (preg_match('/^(\d)\1{8}$/', $base) === 1) {
            $base = ((int) $base[0] + 1) % 10 .substr($base, 1);
        }

        return $base.$this->digitosVerificadores($base);
    }

    /** Mesmo algoritmo público de App\Support\Cpf::isValid, na direção da geração. */
    private function digitosVerificadores(string $base): string
    {
        $digitos = $base;
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digitos[$i] * (($position + 1) - $i);
            }
            $digitos .= (string) (((10 * $sum) % 11) % 10);
        }

        return substr($digitos, 9, 2);
    }
}
