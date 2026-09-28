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
 * Informe en Markdown para pegar en un chat con un asistente de IA.
 * Solo datos (sin interpretación propia) para no sesgar el análisis.
 */
class ReportGenerator
{
    public const RANGES = [7, 14, 30];

    public const QUESTION = 'Analiza mi progreso, dime qué patrones ves, qué está funcionando, qué no, y dame un solo ajuste concreto para la próxima semana.';

    public function __construct(private HabitStatsService $stats) {}

    public function generate(User $user, int $days, ?CarbonImmutable $today = null): string
    {
        if (! in_array($days, self::RANGES, true)) {
            throw new \InvalidArgumentException("Rango no soportado: {$days}");
        }

        $today ??= LocalDate::today();
        $to = $today;
        $from = $today->subDays($days - 1);
        $fromKey = $from->format('Y-m-d');
        $toKey = $to->format('Y-m-d');

        $habits = $user->habits()->active()->ordered()
            ->where('start_date', '<=', $toKey)
            ->with(['logs' => fn ($q) => $q->where('completed', true)->select('id', 'habit_id', 'date', 'completed')])
            ->get();

        $md = [];
        $md[] = "# Informe de hábitos: {$this->long($from)} al {$this->long($to)} ({$days} días)";
        $md[] = '';
        $md[] = '> Datos de mi app personal de hábitos. Zona horaria '.LocalDate::timezone().'. '
            .'Hábitos diarios: % de días cumplidos (un día sin marcar cuenta como no cumplido; hoy solo cuenta si ya lo marqué). '
            .'Hábitos semanales: % de semanas (lunes a domingo) en que llegué a la meta; la semana en curso solo cuenta si ya la cumplí.';
        $md[] = '';

        $md = [...$md, ...$this->summary($user, $habits, $from, $to, $days)];
        $md = [...$md, ...$this->habitsTable($habits, $from, $to)];
        $md = [...$md, ...$this->weeklyDetail($habits, $from, $to)];
        $md = [...$md, ...$this->weekdayTable($habits, $from, $to)];
        $md = [...$md, ...$this->priorities($user, $fromKey, $toKey)];
        $md = [...$md, ...$this->notes($habits, $fromKey, $toKey)];

        $md[] = '---';
        $md[] = '';
        $md[] = self::QUESTION;

        return implode("\n", $md)."\n";
    }

    /* ----------------------------------------------------------------- */

    private function summary(User $user, Collection $habits, CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $overall = $this->stats->overallRate($habits, $from, $to, $to);
        $prev = $this->stats->overallRate($habits, $from->subDays($days), $from->subDay(), $to);
        $prio = $this->stats->prioritySummary($user, $from, $to);

        $out = ['## Resumen', ''];
        if ($habits->isEmpty()) {
            $out[] = '- No tengo hábitos activos en este periodo.';
        } else {
            $daily = $habits->reject->isWeekly()->count();
            $weekly = $habits->count() - $daily;
            $out[] = '- Hábitos activos: '.$habits->count()." ({$daily} ".($daily === 1 ? 'diario' : 'diarios').", {$weekly} ".($weekly === 1 ? 'semanal' : 'semanales').')';
            $out[] = '- Cumplimiento global: '.$this->pct($overall).($prev['rate'] !== null ? " · periodo anterior ({$days} días): ".$this->pct($prev) : '');
        }
        $out[] = '- Prioridades: '.$prio['completed'].' cumplidas, '.$prio['not_completed'].' no cumplidas, '.$prio['unmarked'].' sin marcar';
        $out[] = '- Días sin ningún registro: '.$this->emptyDays($user, $from, $to).' de '.$days;
        $out[] = '';

        return $out;
    }

    private function habitsTable(Collection $habits, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($habits->isEmpty()) {
            return [];
        }

        $out = ['## Hábitos', '', '| Hábito | Frecuencia | Cumplimiento | Racha actual | Mejor racha |', '|---|---|---|---|---|'];
        foreach ($habits as $h) {
            $r = $this->stats->completionRate($h, $from, $to, $to);
            $unit = $h->isWeekly() ? 'semanas' : 'días';
            $detail = $r['total'] ? " ({$r['done']}/{$r['total']} {$unit})" : '';
            $out[] = sprintf(
                '| %s | %s | %s%s | %s | %s |',
                $this->cell($h->name),
                $h->frequencyLabel(),
                $this->pct($r),
                $detail,
                $this->streak($this->stats->currentStreak($h, $to), $h),
                $this->streak($this->stats->bestStreak($h, $to), $h),
            );
        }
        $out[] = '';

        $starts = $habits->filter(fn (Habit $h) => $h->startDate() > $from->format('Y-m-d'));
        foreach ($starts as $h) {
            $out[] = "- {$this->inline($h->name)} empezó el {$this->short($h->startDate())}; antes no cuenta.";
        }
        if ($starts->isNotEmpty()) {
            $out[] = '';
        }

        return $out;
    }

    private function weeklyDetail(Collection $habits, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekly = $habits->filter->isWeekly();
        if ($weekly->isEmpty()) {
            return [];
        }

        $out = ['## Detalle de hábitos semanales', ''];
        foreach ($weekly as $h) {
            $parts = array_map(
                fn ($p) => 'semana del '.$this->short($p['start']).": {$p['done']}/{$p['target']}".($p['evaluated'] ? '' : ' (en curso)'),
                $this->stats->periods($h, $from, $to, $to)
            );
            $out[] = "- {$this->inline($h->name)}: ".($parts ? implode(' · ', $parts) : 'sin semanas en el periodo');
        }
        $out[] = '';

        return $out;
    }

    private function weekdayTable(Collection $habits, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($habits->reject->isWeekly()->isEmpty()) {
            return [];
        }

        $rows = $this->stats->weekdayBreakdown($habits, $from, $to, $to);
        $out = ['## Patrón por día de la semana (solo hábitos diarios)', '', '| Día | Cumplimiento |', '|---|---|'];
        foreach ($rows as $dow => $r) {
            $out[] = '| '.ucfirst(FeedbackService::WEEKDAYS[$dow]).' | '.$this->pct($r).($r['total'] ? " ({$r['done']}/{$r['total']})" : '').' |';
        }

        $withData = array_filter($rows, fn ($r) => $r['rate'] !== null);
        if (count($withData) >= 2) {
            uasort($withData, fn ($a, $b) => $a['rate'] <=> $b['rate']);
            $weak = array_key_first($withData);
            $strong = array_key_last($withData);
            $out[] = '';
            $out[] = '- Día más débil: '.FeedbackService::WEEKDAYS[$weak].' ('.$this->pct($withData[$weak]).'). Día más fuerte: '.FeedbackService::WEEKDAYS[$strong].' ('.$this->pct($withData[$strong]).').';
        }
        $out[] = '';

        return $out;
    }

    private function priorities(User $user, string $fromKey, string $toKey): array
    {
        $rows = DailyPriority::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$fromKey, $toKey])
            ->orderBy('date')->orderBy('position')
            ->get()
            ->groupBy('date');

        $out = ['## Prioridades diarias', ''];
        if ($rows->isEmpty()) {
            $out[] = 'No planeé prioridades en este periodo.';
            $out[] = '';

            return $out;
        }

        foreach ($rows as $date => $items) {
            $out[] = "**{$this->short($date)}**";
            foreach ($items as $p) {
                $out[] = match ($p->completed) {
                    true => "- [x] {$this->inline($p->text)}",
                    false => "- [ ] {$this->inline($p->text)} (no cumplida)",
                    default => "- [ ] {$this->inline($p->text)} (sin marcar)",
                };
            }
            $out[] = '';
        }

        return $out;
    }

    private function notes(Collection $habits, string $fromKey, string $toKey): array
    {
        $names = $habits->pluck('name', 'id');
        $logs = HabitLog::query()
            ->whereIn('habit_id', $names->keys())
            ->whereBetween('date', [$fromKey, $toKey])
            ->whereNotNull('note')
            ->orderBy('date')->orderBy('habit_id')
            ->get();

        $out = ['## Mis notas', ''];
        if ($logs->isEmpty()) {
            $out[] = 'Sin notas en este periodo.';
        }
        foreach ($logs as $log) {
            $state = $log->completed ? 'hecho' : 'no hecho';
            $out[] = "- {$this->short($log->date)} · {$this->inline($names[$log->habit_id])} ({$state}): {$this->inline($log->note)}";
        }
        $out[] = '';

        return $out;
    }

    private function emptyDays(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $range = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        $active = HabitLog::query()
            ->whereIn('habit_id', $user->habits()->select('id'))
            ->where('completed', true)
            ->whereBetween('date', $range)
            ->distinct()->pluck('date')
            ->merge(DailyPriority::query()->where('user_id', $user->id)->whereNotNull('completed')->whereBetween('date', $range)->distinct()->pluck('date'))
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique();

        return (int) $from->diffInDays($to) + 1 - $active->count();
    }

    /* ---------------- Formato ---------------- */

    private function pct(array $r): string
    {
        return $r['rate'] === null ? 'sin datos' : round($r['rate']).'%';
    }

    private function streak(int $n, Habit $h): string
    {
        return $h->isWeekly()
            ? $n.' '.($n === 1 ? 'semana' : 'semanas')
            : $n.' '.($n === 1 ? 'día' : 'días');
    }

    /** "lun 21 sep" */
    private function short(string $date): string
    {
        return str_replace('.', '', LocalDate::parse($date)->locale('es')->isoFormat('ddd D MMM'));
    }

    /** "21 de septiembre de 2026" */
    private function long(CarbonImmutable $d): string
    {
        return $d->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }

    /** Texto de usuario en una línea (sin saltos que rompan listas). */
    private function inline(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Texto de usuario dentro de una celda de tabla. */
    private function cell(string $text): string
    {
        return str_replace('|', '\|', $this->inline($text));
    }
}
