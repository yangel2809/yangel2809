<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Punto único para obtener fechas locales del usuario (America/Caracas).
 * Todas las fechas de negocio se manejan como 'Y-m-d' en esta zona.
 */
final class LocalDate
{
    public static function timezone(): string
    {
        return config('habits.timezone', 'America/Caracas');
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    public static function parse(string|CarbonInterface $date): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            $date = $date->format('Y-m-d');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());
    }

    public static function weekStart(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /** Días hacia atrás que se pueden seguir marcando (hoy incluido: 7). */
    public const EDITABLE_DAYS = 7;

    /**
     * Fecha 'Y-m-d' válida para registrar: entre hoy-6 y hoy.
     */
    public static function isEditable(?string $date): bool
    {
        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return false;
        }
        $today = self::today();

        return $date <= $today->format('Y-m-d')
            && $date >= $today->subDays(self::EDITABLE_DAYS - 1)->format('Y-m-d');
    }

    /** "martes 29 de septiembre" */
    public static function human(string|CarbonInterface $date): string
    {
        return self::parse($date)->locale('es')->isoFormat('dddd D [de] MMMM');
    }
}
