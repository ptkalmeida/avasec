<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

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

    public static function quizPassThreshold(): int
    {
        return self::intConfig('constants.quiz_pass_threshold', 70);
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

    public static function liveSessionAutoEndHours(): int
    {
        return self::intConfig('constants.live_session_auto_end_hours', 24);
    }

    /**
     * A transmissão agendada para `$scheduledAt` já passou da janela de
     * encerramento automático.
     *
     * `$scheduledAt` é ISO local sem fuso (`2026-09-15T19:30`) — o que o
     * `<input type="datetime-local">` produz e o que o banco guarda. Data em
     * outro formato (texto livre do modelo antigo, "Próxima Segunda, às 20:00")
     * devolve false: não há de onde contar, e chutar encerraria um encontro que
     * talvez ainda vá acontecer.
     */
    public static function liveSessionExpired(?string $scheduledAt, ?CarbonImmutable $agora = null): bool
    {
        if (! is_string($scheduledAt)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', trim($scheduledAt)) !== 1) {
            return false;
        }

        try {
            $quando = CarbonImmutable::parse(trim($scheduledAt));
        } catch (\Throwable) {
            return false;
        }

        $referencia = $agora ?? CarbonImmutable::now();

        return $referencia->getTimestamp() - $quando->getTimestamp()
            >= self::liveSessionAutoEndHours() * 3600;
    }
}
