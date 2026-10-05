<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\SystemSettings;

/**
 * Configurações do sistema (linha singleton) — espelha src/server/services/settingsService.ts.
 * Atualização faz merge raso, restrito às chaves conhecidas.
 *
 * Por que existe a lista de chaves: GET /api/system-settings é PÚBLICO de propósito
 * (o visitante anônimo monta o site com isso, igual a /site-content), e o merge era de
 * formato livre. Ou seja, qualquer chave que um admin gravasse ali nascia pública —
 * inclusive uma que ninguém pretendesse publicar. Nada era exposto hoje, mas a porta
 * estava aberta para o próximo campo. Barrar na ESCRITA é melhor que filtrar na leitura:
 * o segredo não chega a ser gravado.
 */
final class SettingsService
{
    /**
     * Chaves permitidas — réplica manual de `systemSettings` em src/context/LMSContext.tsx,
     * mesmo pacto de config/features.php ↔ src/config/features.ts, e travada por teste.
     * Toda chave aqui é PÚBLICA por definição: nunca acrescente segredo, token, e-mail,
     * caminho interno ou qualquer dado pessoal a esta lista.
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        'allowDirectMessages',
        'allowGlobalChat',
        'openEnrollment',
        'autoCertify',
        'autoArchiveDuration',
        'liveClassRecording',
    ];

    /** @return array<string, mixed> */
    public function get(): array
    {
        $row = SystemSettings::query()->find('singleton');

        // Filtra também na leitura: a linha pode ter sido gravada antes desta regra.
        return array_intersect_key($row->data ?? [], array_flip(self::ALLOWED_KEYS));
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    public function update(array $updates): array
    {
        $desconhecidas = array_diff(array_keys($updates), self::ALLOWED_KEYS);
        if ($desconhecidas !== []) {
            throw ApiException::validation(
                'Configuração desconhecida: '.implode(', ', $desconhecidas)
                .'. Estas configurações são públicas; para adicionar uma chave, inclua-a em SettingsService::ALLOWED_KEYS.'
            );
        }

        $row = SystemSettings::query()->find('singleton');
        $merged = array_merge($row->data ?? [], $updates);

        if ($row !== null) {
            $row->data = $merged;
            $row->save();
        } else {
            SystemSettings::query()->create(['id' => 'singleton', 'data' => $merged]);
        }

        return $merged;
    }
}
