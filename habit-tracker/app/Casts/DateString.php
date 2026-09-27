<?php

namespace App\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Fecha local como string 'Y-m-d' en ambos sentidos.
 *
 * El cast 'date' de Eloquent serializa como 'Y-m-d H:i:s' y hace
 * conversiones de zona horaria; para fechas de calendario (sin hora)
 * queremos el valor literal, idéntico en MySQL y SQLite.
 */
class DateString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 10);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        throw new InvalidArgumentException("Fecha inválida para {$key}");
    }
}
