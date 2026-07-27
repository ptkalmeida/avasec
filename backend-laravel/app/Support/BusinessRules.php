<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Regras numéricas de negócio — espelha src/config/constants.ts.
 */
final class BusinessRules
{
    public static function defaultMinAttendance(): int
    {
        return self::intConfig('constants.default_min_attendance', 70);
    }

    public static function dropoutPenaltyFreeDays(): int
    {
        return self::intConfig('constants.dropout_penalty_free_days', 5);
    }

    public static function dropoutPenaltyDays(): int
    {
        return self::intConfig('constants.dropout_penalty_days', 30);
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Percentual mínimo efetivo de um curso. Usa null coalescing (??), não "ou-lógico":
     * um curso configurado explicitamente com 0% é respeitado (igual ao ?? do Node).
     */
    public static function courseMinAttendance(?int $courseMinAttendance): int
    {
        return $courseMinAttendance ?? self::defaultMinAttendance();
    }
}
