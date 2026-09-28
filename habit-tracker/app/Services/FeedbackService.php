<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\User;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Retroalimentación automática por reglas (sin IA). Cada regla devuelve
 * a lo sumo un mensaje; se muestran en orden de relevancia, máximo MAX.
 *
 * Tono: directo, sin exagerar. Números redondeados a enteros.
 */
class FeedbackService
{
    public const MAX = 5;

    public const WEEKDAYS = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    public function __construct(private HabitStatsService $stats) {}

    /**
     * @param  Collection<int, Habit>|null  $habits  hábitos activos (con logs precargados idealmente)
     * @return list<array{key: string, tone: 'good'|'warn'|'info', text: string}>
     */
    public function messages(User $user, ?Collection $habits = null, ?CarbonImmutable $today = null): array
    {
        $today ??= LocalDate::today();
        $habits ??= $user->habits()->active()->ordered()->with(['logs' => fn ($q) => $q->where('completed', true)])->get();
        $habits = $habits->filter(fn (Habit $h) => $h->startDate() <= $today->format('Y-m-d'))->values();

        if ($habits->isEmpty()) {
            return [];
        }

        $rules = [
            fn () => $this->inactivity($user, $habits, $today),
            fn () => $this->weekComparison($habits, $today),
            fn () => $this->streaks($habits, $today),
            fn () => $this->weakestWeekday($habits, $today),
            fn () => $this->weeklyPending($habits, $today),
            fn () => $this->struggling($habits, $today),
            fn () => $this->priorities($user, $today),
            fn () => $this->solid($habits, $today),
        ];

        $out = [];
        foreach ($rules as $rule) {
            foreach ((array) $rule() as $msg) {
                $out[] = $msg;
            }
        }

        if ($out === []) {
            $out[] = $this->msg('start', 'info', 'Sigue registrando. Con una o dos semanas de datos empiezan a verse los patrones.');
        }

        return array_slice($out, 0, self::MAX);
    }

    /* ----------------------------------------------------------------- */

    private function inactivity(User $user, Collection $habits, CarbonImmutable $today): array
    {
        $days = $this->stats->daysSinceLastActivity($user, $today);

        if ($days === null) {
            $oldest = $habits->min(fn (Habit $h) => $h->startDate());

            return $oldest < $today->format('Y-m-d')
                ? [$this->msg('inactive', 'warn', 'Aún no has registrado nada. Empieza marcando un hábito hoy.')]
                : [];
        }

        if ($days >= 2) {
            return [$this->msg('inactive', 'warn', "No has registrado nada en {$days} días. No hace falta ponerse al día: marca lo de hoy y sigue.")];
        }

        return [];
    }

    private function weekComparison(Collection $habits, CarbonImmutable $today): array
    {
        $monday = LocalDate::weekStart($today);
        $now = $this->stats->overallRate($habits, $monday, $today, $today);
        $prev = $this->stats->overallRate($habits, $monday->subWeek(), $monday->subDay(), $today);

        if ($now['rate'] === null || $prev['rate'] === null) {
            return [];
        }

        $a = (int) round($now['rate']);
        $b = (int) round($prev['rate']);
        $text = "Esta semana vas en {$a}% vs {$b}% la semana pasada.";
        $left = 7 - $today->isoWeekday();

        if ($a - $b >= 5) {
            return [$this->msg('week', 'good', $text.' Vas mejor; mantén el ritmo.')];
        }
        if ($b - $a >= 5) {
            $tail = $left > 0 ? " Quedan {$left} ".($left === 1 ? 'día' : 'días').' para recuperar.' : '';

            return [$this->msg('week', 'warn', $text.$tail)];
        }

        return [$this->msg('week', 'info', $text.' Estable.')];
    }

    private function streaks(Collection $habits, CarbonImmutable $today): array
    {
        $rows = $habits->map(fn (Habit $h) => [
            'habit' => $h,
            'current' => $this->stats->currentStreak($h, $today),
            'best' => $this->stats->bestStreak($h, $today),
        ])->filter(fn ($r) => $r['current'] >= ($r['habit']->isWeekly() ? 2 : 3))
            ->sortByDesc('current')
            ->take(2);

        return $rows->map(function ($r) {
            $h = $r['habit'];
            $n = $r['current'];
            $text = $h->isWeekly()
                ? "Llevas {$n} semanas seguidas cumpliendo {$h->name}."
                : "Llevas {$n} días seguidos con {$h->name}.";
            if ($n >= $r['best'] && $n >= ($h->isWeekly() ? 3 : 5)) {
                $text .= ' Es tu mejor racha.';
            }

            return $this->msg('streak:'.$h->id, 'good', $text);
        })->values()->all();
    }

    private function weakestWeekday(Collection $habits, CarbonImmutable $today): array
    {
        $days = collect($this->stats->weekdayBreakdown($habits, $today->subDays(29), $today, $today))
            ->filter(fn ($d) => $d['total'] >= 3);

        if ($days->count() < 3) {
            return [];
        }

        $done = $days->sum('done');
        $total = $days->sum('total');
        $avg = $done / $total * 100;
        $weakest = $days->sortBy('rate')->keys()->first();
        $rate = $days[$weakest]['rate'];

        if ($avg - $rate < 15) {
            return [];
        }

        return [$this->msg('weekday', 'warn', 'Tu día más débil es el '.self::WEEKDAYS[$weakest].': cumples solo el '.round($rate).'% (tu promedio es '.round($avg).'%).')];
    }

    private function weeklyPending(Collection $habits, CarbonImmutable $today): array
    {
        $monday = LocalDate::weekStart($today);
        $out = [];

        foreach ($habits->filter(fn (Habit $h) => $h->isWeekly()) as $h) {
            $count = $this->stats->weekCount($h, $monday);
            $need = $h->weekly_target - $count;
            if ($need <= 0) {
                continue;
            }
            $doneToday = isset($this->stats->doneDates($h)[$today->format('Y-m-d')]);
            $available = (8 - $today->isoWeekday()) - ($doneToday ? 1 : 0);

            if ($need > $available) {
                $out[] = $this->msg('weekly:'.$h->id, 'warn', "Esta semana ya no llegas a la meta de {$h->name} ({$count}/{$h->weekly_target}). Suma lo que puedas; la próxima empieza de cero.");
            } elseif ($available - $need <= 1) {
                $out[] = $this->msg('weekly:'.$h->id, 'info', "Te ".($need === 1 ? 'falta 1' : "faltan {$need}")." de {$h->name} y ".($available === 1 ? 'queda 1 día' : "quedan {$available} días").'.');
            }
        }

        return $out;
    }

    private function struggling(Collection $habits, CarbonImmutable $today): array
    {
        $worst = $habits
            ->reject(fn (Habit $h) => $h->isWeekly())
            ->map(fn (Habit $h) => ['habit' => $h, 'r' => $this->stats->lastDaysRate($h, 7, $today)])
            ->filter(fn ($x) => $x['r']['total'] >= 5 && $x['r']['rate'] < 40)
            ->sortBy(fn ($x) => $x['r']['rate'])
            ->first();

        if (! $worst) {
            return [];
        }

        return [$this->msg('struggling', 'warn', "{$worst['habit']->name} va en ".round($worst['r']['rate']).'% los últimos 7 días. Si la meta es muy alta, ajústala en vez de abandonarla.')];
    }

    private function priorities(User $user, CarbonImmutable $today): array
    {
        $s = $this->stats->prioritySummary($user, $today->subDays(6), $today);
        $marked = $s['completed'] + $s['not_completed'];

        if ($marked < 3) {
            return [];
        }

        $tone = $s['rate'] >= 70 ? 'good' : ($s['rate'] < 40 ? 'warn' : 'info');
        $text = "Cumpliste {$s['completed']} de {$marked} prioridades en los últimos 7 días.";
        if ($tone === 'warn') {
            $text .= ' Prueba con menos o más pequeñas.';
        }

        return [$this->msg('priorities', $tone, $text)];
    }

    private function solid(Collection $habits, CarbonImmutable $today): array
    {
        $best = $habits
            ->map(fn (Habit $h) => ['habit' => $h, 'r' => $this->stats->lastDaysRate($h, 30, $today)])
            ->filter(fn ($x) => $x['r']['total'] >= ($x['habit']->isWeekly() ? 3 : 14) && $x['r']['rate'] >= 90)
            ->sortByDesc(fn ($x) => $x['r']['rate'])
            ->first();

        if (! $best) {
            return [];
        }

        return [$this->msg('solid', 'good', "{$best['habit']->name} está sólido: ".round($best['r']['rate']).'% en 30 días.')];
    }

    private function msg(string $key, string $tone, string $text): array
    {
        return ['key' => $key, 'tone' => $tone, 'text' => $text];
    }
}
