<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSettings;

/**
 * Configurações do sistema (linha singleton, JSON de formato livre definido pelo frontend)
 * — espelha src/server/services/settingsService.ts. Atualização faz merge raso.
 */
final class SettingsService
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        $row = SystemSettings::query()->find('singleton');

        return $row->data ?? [];
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    public function update(array $updates): array
    {
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
