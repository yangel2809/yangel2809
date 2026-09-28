<?php

namespace App\Services;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;

/**
 * Datos de la pantalla "Hoy" para una fecha: hábitos con su estado,
 * nota, racha y progreso semanal, más las prioridades del día.
 * Todo en 3 consultas, sin importar cuántos hábitos haya.
 */
class DayBoard
{
    public function __construct(private HabitStatsService $stats) {}

    /**
     * @return array{habits: list<array>, priorities: list<array>, done: int, total: int}
     */
    public function build(User $user, CarbonImmutable $day): array
    {
        $key = $day->format('Y-m-d');

        $habits = $user->habits()
            ->active()
            ->ordered()
            ->where('start_date', '<=', $key)
            ->with(['logs' => fn ($q) => $q->where('completed', true)->select('id', 'habit_id', 'date', 'completed')])
            ->get();

        $dayLogs = HabitLog::query()
            ->whereIn('habit_id', $habits->pluck('id'))
            ->where('date', $key)
            ->get()
            ->keyBy('habit_id');

        $items = $habits->map(fn (Habit $h) => $this->habitItem($h, $dayLogs->get($h->id), $day))->values()->all();

        $priorities = DailyPriority::query()
            ->where('user_id', $user->id)
            ->where('date', $key)
            ->orderBy('position')
            ->get()
            ->map(fn (DailyPriority $p) => $this->priorityItem($p))
            ->all();

        return [
            'habits' => $items,
            'priorities' => $priorities,
            'done' => count(array_filter($items, fn ($i) => $i['done'])),
            'total' => count($items),
        ];
    }

    public function habitItem(Habit $habit, ?HabitLog $log, CarbonImmutable $day): array
    {
        $this->stats->forget($habit);
        $weekly = $habit->isWeekly();

        return [
            'id' => $habit->id,
            'name' => $habit->name,
            'color' => $habit->color,
            'weekly' => $weekly,
            'target' => $weekly ? $habit->weekly_target : null,
            'week_count' => $weekly ? $this->stats->weekCount($habit, LocalDate::weekStart($day)) : null,
            'done' => (bool) $log?->completed,
            'note' => $log?->note,
            'streak' => $this->stats->currentStreak($habit, $day),
            'streak_unit' => $weekly ? 'sem' : 'd',
        ];
    }

    public function priorityItem(DailyPriority $p): array
    {
        return ['id' => $p->id, 'position' => $p->position, 'text' => $p->text, 'completed' => $p->completed];
    }
}
