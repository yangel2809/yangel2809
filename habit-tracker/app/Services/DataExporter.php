<?php

namespace App\Services;

use App\Models\User;
use App\Support\LocalDate;

/**
 * Respaldo completo de los datos del usuario (formato estable, versionado).
 */
class DataExporter
{
    public const FORMAT_VERSION = 1;

    public function export(User $user): array
    {
        $habits = $user->habits()->orderBy('sort_order')->orderBy('id')
            ->with(['logs' => fn ($q) => $q->orderBy('date')])
            ->get();

        return [
            'app' => 'habitos',
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'timezone' => LocalDate::timezone(),
            'user' => ['name' => $user->name, 'email' => $user->email],
            'habits' => $habits->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'frequency_type' => $h->frequency_type,
                'weekly_target' => $h->weekly_target,
                'color' => $h->color,
                'start_date' => $h->start_date,
                'sort_order' => $h->sort_order,
                'archived_at' => $h->archived_at?->toIso8601String(),
                'created_at' => $h->created_at?->toIso8601String(),
                'logs' => $h->logs->map(fn ($l) => [
                    'date' => $l->date,
                    'completed' => $l->completed,
                    'note' => $l->note,
                ])->all(),
            ])->all(),
            'priorities' => $user->priorities()->orderBy('date')->orderBy('position')->get()
                ->map(fn ($p) => [
                    'date' => $p->date,
                    'position' => $p->position,
                    'text' => $p->text,
                    'completed' => $p->completed,
                ])->all(),
        ];
    }
}
