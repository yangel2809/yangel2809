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
}
