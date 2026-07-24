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
        return (int) config('constants.default_min_attendance');
    }

    public static function dropoutPenaltyFreeDays(): int
    {
        return (int) config('constants.dropout_penalty_free_days');
    }

    public static function dropoutPenaltyDays(): int
    {
        return (int) config('constants.dropout_penalty_days');
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
