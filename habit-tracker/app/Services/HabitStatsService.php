<?php

namespace App\Services;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cálculo de cumplimiento, rachas y patrones.
 *
 * Reglas:
 * - Todas las fechas son locales (config habits.timezone) en formato 'Y-m-d'.
 * - Hábito diario: la unidad de evaluación es el día. Un día pasado sin
 *   registro completado cuenta como fallo. Hoy solo cuenta si ya está hecho.
 * - Hábito semanal: la unidad es la semana ISO (lunes a domingo). Cumple si
 *   hay >= weekly_target días completados en la semana. La semana en curso y
 *   la semana parcial en que empezó el hábito solo cuentan si ya se cumplieron.
 * - Nada anterior a start_date se evalúa.
 */
class HabitStatsService
{
    /** @var array<int, array<string, true>> */
    private array $doneCache = [];

    /**
     * Conjunto de fechas completadas del hábito (claves 'Y-m-d').
     *
     * @return array<string, true>
     */
    public function doneDates(Habit $habit): array
    {
        if (isset($this->doneCache[$habit->id])) {
            return $this->doneCache[$habit->id];
        }

        $logs = $habit->relationLoaded('logs')
            ? $habit->logs->where('completed', true)
            : $habit->logs()->where('completed', true)->get(['date']);

        $set = [];
        foreach ($logs as $log) {
            if ($log->date >= $habit->startDate()) {
                $set[$log->date] = true;
            }
        }

        return $this->doneCache[$habit->id] = $set;
    }

    public function forget(?Habit $habit = null): void
    {
        if ($habit) {
            unset($this->doneCache[$habit->id]);
        } else {
            $this->doneCache = [];
        }
    }

    /**
     * Periodos (días o semanas) del hábito que tocan el rango [from, to],
     * recortado a [start_date, today].
     *
     * @return list<array{start: string, end: string, done: int, target: int, met: bool, evaluated: bool}>
     */
    public function periods(Habit $habit, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array
    {
        $today = $this->day($today ?? LocalDate::today());
        $start = LocalDate::parse($habit->startDate());
        $from = $this->day($from)->max($start);
        $to = $this->day($to)->min($today);

        if ($from->gt($to)) {
            return [];
        }

        return $habit->isWeekly()
            ? $this->weeklyPeriods($habit, $from, $to, $today, $start)
            : $this->dailyPeriods($habit, $from, $to, $today);
    }

    /**
     * @return array{done: int, total: int, rate: float|null}
     */
    public function completionRate(Habit $habit, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array
    {
        $evaluated = array_filter($this->periods($habit, $from, $to, $today), fn ($p) => $p['evaluated']);
        $done = count(array_filter($evaluated, fn ($p) => $p['met']));

        return $this->rate($done, count($evaluated));
    }

    /**
     * Cumplimiento de los últimos N días (incluyendo hoy).
     *
     * @return array{done: int, total: int, rate: float|null}
     */
    public function lastDaysRate(Habit $habit, int $days, ?CarbonImmutable $today = null): array
    {
        $today = $this->day($today ?? LocalDate::today());

        return $this->completionRate($habit, $today->subDays($days - 1), $today, $today);
    }

    /**
     * Racha actual: días (o semanas) consecutivos cumplidos hasta hoy.
     * Si hoy/esta semana aún no se cumple, la racha se cuenta desde el
     * periodo anterior (no se rompe hasta que el periodo termina).
     */
    public function currentStreak(Habit $habit, ?CarbonImmutable $today = null): int
    {
        $today = $this->day($today ?? LocalDate::today());
        $start = LocalDate::parse($habit->startDate());
        $done = $this->doneDates($habit);

        if ($habit->isWeekly()) {
            $week = LocalDate::weekStart($today);
            if (! $this->weekMet($habit, $week)) {
                $week = $week->subWeek();
            }
            $streak = 0;
            while ($week->addDays(6)->gte($start) && $this->weekMet($habit, $week)) {
                $streak++;
                $week = $week->subWeek();
            }

            return $streak;
        }

        $day = isset($done[$today->format('Y-m-d')]) ? $today : $today->subDay();
        $streak = 0;
        while ($day->gte($start) && isset($done[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * Mejor racha histórica (días o semanas según la frecuencia).
     */
    public function bestStreak(Habit $habit, ?CarbonImmutable $today = null): int
    {
        $todayStr = $this->day($today ?? LocalDate::today())->format('Y-m-d');
        $dates = array_keys(array_filter(
            $this->doneDates($habit),
            fn ($_, $d) => $d <= $todayStr,
            ARRAY_FILTER_USE_BOTH
        ));
        sort($dates);

        if ($habit->isWeekly()) {
            $perWeek = [];
            foreach ($dates as $d) {
                $w = LocalDate::weekStart(LocalDate::parse($d))->format('Y-m-d');
                $perWeek[$w] = ($perWeek[$w] ?? 0) + 1;
            }
            $metWeeks = array_keys(array_filter($perWeek, fn ($n) => $n >= $this->target($habit)));

            return $this->longestRun($metWeeks, 7);
        }

        return $this->longestRun($dates, 1);
    }

    /**
     * Cumplimiento por día de la semana (1 = lunes ... 7 = domingo).
     * Solo hábitos diarios: en los semanales un día sin hacer no es fallo.
     *
     * @param  iterable<Habit>  $habits
     * @return array<int, array{done: int, total: int, rate: float|null}>
     */
    public function weekdayBreakdown(iterable $habits, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array
    {
        $acc = array_fill(1, 7, ['done' => 0, 'total' => 0]);

        foreach ($habits as $habit) {
            if ($habit->isWeekly()) {
                continue;
            }
            foreach ($this->periods($habit, $from, $to, $today) as $p) {
                if (! $p['evaluated']) {
                    continue;
                }
                $dow = LocalDate::parse($p['start'])->isoWeekday();
                $acc[$dow]['total']++;
                $acc[$dow]['done'] += $p['met'] ? 1 : 0;
            }
        }

        return array_map(fn ($a) => $this->rate($a['done'], $a['total']), $acc);
    }

    /**
     * Mapa de calor diario. 'rate' usa solo hábitos diarios (los que se
     * esperan ese día); 'weekly_done' cuenta registros de hábitos semanales.
     *
     * @param  iterable<Habit>  $habits
     * @return list<array{date: string, done: int, total: int, rate: float|null, weekly_done: int, future: bool}>
     */
    public function heatmap(iterable $habits, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array
    {
        $today = $this->day($today ?? LocalDate::today());
        $habits = collect($habits);
        $out = [];

        for ($d = $this->day($from); $d->lte($to); $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $done = $total = $weekly = 0;
            foreach ($habits as $habit) {
                if ($habit->startDate() > $key) {
                    continue;
                }
                $isDone = isset($this->doneDates($habit)[$key]);
                if ($habit->isWeekly()) {
                    $weekly += $isDone ? 1 : 0;
                    continue;
                }
                // Hoy sin marcar no penaliza.
                if ($d->eq($today) && ! $isDone) {
                    continue;
                }
                $total++;
                $done += $isDone ? 1 : 0;
            }
            $future = $d->gt($today);
            $r = $this->rate($done, $future ? 0 : $total);
            $out[] = ['date' => $key, 'done' => $done, 'total' => $total, 'rate' => $r['rate'], 'weekly_done' => $weekly, 'future' => $future];
        }

        return $out;
    }

    /**
     * Cumplimiento agregado de varios hábitos en el rango: suma de periodos
     * cumplidos / periodos evaluados.
     *
     * @param  iterable<Habit>  $habits
     * @return array{done: int, total: int, rate: float|null}
     */
    public function overallRate(iterable $habits, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array
    {
        $done = $total = 0;
        foreach ($habits as $habit) {
            $r = $this->completionRate($habit, $from, $to, $today);
            $done += $r['done'];
            $total += $r['total'];
        }

        return $this->rate($done, $total);
    }

    /**
     * Días desde el último registro (hábito completado o prioridad evaluada).
     * 0 = registró algo hoy; null = nunca ha registrado nada.
     */
    public function daysSinceLastActivity(User $user, ?CarbonImmutable $today = null): ?int
    {
        $today = $this->day($today ?? LocalDate::today());

        $lastLog = HabitLog::query()
            ->whereIn('habit_id', $user->habits()->select('id'))
            ->where('completed', true)
            ->where('date', '<=', $today->format('Y-m-d'))
            ->max('date');

        $lastPriority = DailyPriority::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed')
            ->where('date', '<=', $today->format('Y-m-d'))
            ->max('date');

        $last = max((string) $lastLog, (string) $lastPriority);
        if ($last === '') {
            return null;
        }

        return (int) LocalDate::parse(substr($last, 0, 10))->diffInDays($today);
    }

    /**
     * Resumen de prioridades en el rango.
     *
     * @return array{completed: int, not_completed: int, unmarked: int, total: int, rate: float|null}
     */
    public function prioritySummary(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DailyPriority::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['completed']);

        $completed = $rows->whereStrict('completed', true)->count();
        $notCompleted = $rows->whereStrict('completed', false)->count();
        $unmarked = $rows->whereNull('completed')->count();

        return [
            'completed' => $completed,
            'not_completed' => $notCompleted,
            'unmarked' => $unmarked,
            'total' => $rows->count(),
            'rate' => $this->rate($completed, $completed + $notCompleted)['rate'],
        ];
    }

    /**
     * Resumen listo para vistas/informe por hábito.
     *
     * @param  Collection<int, Habit>  $habits
     * @return list<array{habit: Habit, rate7: array, rate30: array, current: int, best: int, unit: string}>
     */
    public function summary(Collection $habits, ?CarbonImmutable $today = null): array
    {
        $today = $this->day($today ?? LocalDate::today());

        return $habits->map(fn (Habit $h) => [
            'habit' => $h,
            'rate7' => $this->lastDaysRate($h, 7, $today),
            'rate30' => $this->lastDaysRate($h, 30, $today),
            'current' => $this->currentStreak($h, $today),
            'best' => $this->bestStreak($h, $today),
            'unit' => $h->isWeekly() ? 'semanas' : 'días',
        ])->values()->all();
    }

    /* ----------------------------------------------------------------- */

    private function dailyPeriods(Habit $habit, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today): array
    {
        $done = $this->doneDates($habit);
        $out = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $met = isset($done[$key]);
            $out[] = [
                'start' => $key,
                'end' => $key,
                'done' => $met ? 1 : 0,
                'target' => 1,
                'met' => $met,
                'evaluated' => $met || $d->lt($today),
            ];
        }

        return $out;
    }

    private function weeklyPeriods(Habit $habit, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today, CarbonImmutable $start): array
    {
        $out = [];
        for ($w = LocalDate::weekStart($from); $w->lte($to); $w = $w->addWeek()) {
            $end = $w->addDays(6);
            $count = $this->weekCount($habit, $w);
            $met = $count >= $this->target($habit);
            // Semana cerrada y completa (el hábito existía desde el lunes).
            $complete = $end->lt($today) && $w->gte($start);
            $out[] = [
                'start' => $w->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'done' => $count,
                'target' => $this->target($habit),
                'met' => $met,
                'evaluated' => $met || $complete,
            ];
        }

        return $out;
    }

    public function weekCount(Habit $habit, CarbonImmutable $weekStart): int
    {
        $done = $this->doneDates($habit);
        $n = 0;
        for ($i = 0; $i < 7; $i++) {
            if (isset($done[$weekStart->addDays($i)->format('Y-m-d')])) {
                $n++;
            }
        }

        return $n;
    }

    private function weekMet(Habit $habit, CarbonImmutable $weekStart): bool
    {
        return $this->weekCount($habit, $weekStart) >= $this->target($habit);
    }

    private function target(Habit $habit): int
    {
        return max(1, (int) $habit->weekly_target);
    }

    /**
     * Longitud de la corrida más larga de fechas ordenadas separadas por $step días.
     *
     * @param  list<string>  $sortedDates
     */
    private function longestRun(array $sortedDates, int $step): int
    {
        $best = $run = 0;
        $prev = null;
        foreach ($sortedDates as $d) {
            $cur = LocalDate::parse($d);
            $run = ($prev && (int) $prev->diffInDays($cur) === $step) ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $cur;
        }

        return $best;
    }

    /**
     * @return array{done: int, total: int, rate: float|null}
     */
    private function rate(int $done, int $total): array
    {
        return [
            'done' => $done,
            'total' => $total,
            'rate' => $total > 0 ? round($done / $total * 100, 1) : null,
        ];
    }

    private function day(CarbonImmutable $d): CarbonImmutable
    {
        return LocalDate::parse($d->format('Y-m-d'));
    }
}
