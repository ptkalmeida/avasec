<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Rotação das senhas de demonstração (SEC-02 da auditoria de 31/08/2026).
 *
 * Os PINs 1234 / 5678 / 9999 saíram do código, mas continuam VÁLIDOS nas contas e
 * estão no histórico do git — a correção de código não os invalida. Trocá-los é
 * operação sobre dado real, então é um comando que uma pessoa executa e acompanha,
 * nunca algo que roda em migration ou deploy automático.
 *
 * A senha nova é gerada aqui e mostrada UMA vez: o banco guarda só o hash.
 *
 * Uso:
 *   php artisan avasec:rotate-demo-passwords --dry-run   (lista o que seria trocado)
 *   php artisan avasec:rotate-demo-passwords
 */
final class RotateDemoPasswords extends Command
{
    protected $signature = 'avasec:rotate-demo-passwords
        {--dry-run : Apenas lista as contas com senha de demonstração, sem alterar nada}';

    protected $description = 'Troca as senhas de demonstração (1234/5678/9999) por senhas aleatórias fortes';

    /** PINs de demonstração que este comando procura e substitui. */
    private const DEMO_PASSWORDS = ['1234', '5678', '9999'];

    public function handle(): int
    {
        $afetadas = [];
        foreach (User::query()->get() as $user) {
            foreach (self::DEMO_PASSWORDS as $pin) {
                if (is_string($user->passwordHash) && password_verify($pin, $user->passwordHash)) {
                    $afetadas[] = $user;
                    break;
                }
            }
        }

        if ($afetadas === []) {
            $this->info('Nenhuma conta usa senha de demonstração. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($afetadas).' conta(s) com senha de demonstração:');
        foreach ($afetadas as $user) {
            $this->line("  - {$user->name} ({$user->role}) — {$user->email}");
        }
        $this->newLine();

        if ($this->option('dry-run') === true) {
            $this->info('--dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        // Sem confirmação não roda: quem executa precisa ter onde anotar as senhas
        // novas antes de perder o acesso às contas.
        if (! $this->confirm('Trocar a senha destas contas agora? As senhas novas serão exibidas UMA vez.', false)) {
            $this->info('Cancelado. Nada foi alterado.');

            return self::SUCCESS;
        }

        $linhas = [];
        foreach ($afetadas as $user) {
            $nova = self::gerarSenha();
            $user->passwordHash = password_hash($nova, PASSWORD_DEFAULT);
            $user->save();
            $linhas[] = [$user->name, $user->email, $nova];
        }

        $this->newLine();
        $this->table(['Conta', 'E-mail', 'Senha nova'], $linhas);
        $this->newLine();
        $this->warn('Anote agora: o banco guarda apenas o hash e estas senhas não podem ser recuperadas.');
        $this->warn('Limpe o histórico do terminal depois de anotar.');

        return self::SUCCESS;
    }

    /**
     * Senha que cumpre StrongPasswordRule (mín. 8, ao menos uma letra e um dígito),
     * sem caracteres ambíguos — ela vai ser ditada ou copiada de tela.
     */
    private static function gerarSenha(): string
    {
        $letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digitos = '23456789';
        $alfabeto = $letras.$digitos;

        $caracteres = [
            $letras[random_int(0, strlen($letras) - 1)],
            $digitos[random_int(0, strlen($digitos) - 1)],
        ];
        for ($i = 0; $i < 12; $i++) {
            $caracteres[] = $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        shuffle($caracteres);

        return implode('', $caracteres);
    }
}
