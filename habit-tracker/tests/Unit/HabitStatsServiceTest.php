<?php

namespace Tests\Unit;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\HabitStatsService;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hoy" fijo: jueves 2026-09-24 (America/Caracas).
 * Semana en curso: lun 2026-09-21 .. dom 2026-09-27.
 */
class HabitStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private HabitStatsService $stats;
    private User $user;
    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 21:00:00', 'America/Caracas'));
        $this->stats = new HabitStatsService();
        $this->user = User::factory()->create();
        $this->today = LocalDate::today();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function daily(string $start = '2026-08-01'): Habit
    {
        return Habit::factory()->for($this->user)->startingOn($start)->create();
    }

    private function weekly(int $target, string $start = '2026-08-03'): Habit
    {
        return Habit::factory()->for($this->user)->weekly($target)->startingOn($start)->create();
    }

    private function log(Habit $habit, array $dates, bool $completed = true): void
    {
        foreach ($dates as $d) {
            HabitLog::factory()->for($habit)->create(['date' => $d, 'completed' => $completed]);
        }
        $this->stats->forget($habit);
    }

    /* ---------------- Zona horaria ---------------- */

    public function test_today_uses_caracas_timezone_not_utc(): void
    {
        // 02:30 UTC del 25 = 22:30 del 24 en Caracas (UTC-4).
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 02:30:00', 'UTC'));

        $this->assertSame('2026-09-24', LocalDate::today()->format('Y-m-d'));
    }

    public function test_streak_uses_local_date_near_midnight_utc(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-22', '2026-09-23', '2026-09-24']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 03:00:00', 'UTC'));

        // En UTC ya sería 25 y "hoy" sin marcar; en Caracas sigue siendo 24 y hecho.
        $this->assertSame(3, $this->stats->currentStreak($habit));
    }

    /* ---------------- Rachas diarias ---------------- */

    public function test_current_streak_counts_consecutive_days_including_today(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-20', '2026-09-22', '2026-09-23', '2026-09-24']);

        $this->assertSame(3, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_unmarked_today_does_not_break_current_streak(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-21', '2026-09-22', '2026-09-23']);

        $this->assertSame(3, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_missing_yesterday_breaks_current_streak(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-20', '2026-09-21', '2026-09-22']);

        $this->assertSame(0, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_explicit_not_completed_log_breaks_streak(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-21', '2026-09-23']);
        $this->log($habit, ['2026-09-22'], completed: false);

        $this->assertSame(1, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_best_streak_finds_longest_historical_run(): void
    {
        $habit = $this->daily();
        $this->log($habit, [
            '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05', // 5
            '2026-09-10', '2026-09-11',                                           // 2
            '2026-09-23', '2026-09-24',                                           // 2 (actual)
        ]);

        $this->assertSame(5, $this->stats->bestStreak($habit, $this->today));
        $this->assertSame(2, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_best_streak_across_month_boundary(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-08-30', '2026-08-31', '2026-09-01', '2026-09-02']);

        $this->assertSame(4, $this->stats->bestStreak($habit, $this->today));
    }

    public function test_logs_before_start_date_are_ignored(): void
    {
        $habit = $this->daily(start: '2026-09-22');
        $this->log($habit, ['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23']);

        $this->assertSame(2, $this->stats->currentStreak($habit, $this->today));
        $this->assertSame(2, $this->stats->bestStreak($habit, $this->today));
    }

    public function test_habit_without_logs_has_zero_streaks(): void
    {
        $habit = $this->daily();

        $this->assertSame(0, $this->stats->currentStreak($habit, $this->today));
        $this->assertSame(0, $this->stats->bestStreak($habit, $this->today));
    }

    /* ---------------- Porcentajes diarios ---------------- */

    public function test_daily_rate_counts_unlogged_past_days_as_missed_and_skips_unmarked_today(): void
    {
        $habit = $this->daily();
        // Últimos 7 días: 18..24. Hechos: 18, 20, 22. Hoy (24) sin marcar.
        $this->log($habit, ['2026-09-18', '2026-09-20', '2026-09-22']);

        $r = $this->stats->lastDaysRate($habit, 7, $this->today);

        $this->assertSame(3, $r['done']);
        $this->assertSame(6, $r['total']); // 18..23, hoy no cuenta
        $this->assertSame(50.0, $r['rate']);
    }

    public function test_daily_rate_includes_today_when_done(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-24']);

        $r = $this->stats->lastDaysRate($habit, 7, $this->today);

        $this->assertSame(1, $r['done']);
        $this->assertSame(7, $r['total']);
    }

    public function test_daily_rate_ignores_days_before_start_date(): void
    {
        $habit = $this->daily(start: '2026-09-22');
        $this->log($habit, ['2026-09-22', '2026-09-23']);

        $r = $this->stats->lastDaysRate($habit, 30, $this->today);

        $this->assertSame(2, $r['total']);
        $this->assertSame(100.0, $r['rate']);
    }

    public function test_rate_is_null_when_nothing_to_evaluate(): void
    {
        $habit = $this->daily(start: '2026-09-24'); // creado hoy, sin marcar

        $this->assertNull($this->stats->lastDaysRate($habit, 7, $this->today)['rate']);
    }

    /* ---------------- Hábitos semanales ---------------- */

    public function test_weekly_habit_meeting_target_is_not_penalized_for_skipped_days(): void
    {
        $habit = $this->weekly(3);
        // Semana 14..20: 3 días -> cumple. Semana 7..13: 4 días -> cumple.
        $this->log($habit, ['2026-09-14', '2026-09-16', '2026-09-18', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10']);

        $r = $this->stats->completionRate($habit, LocalDate::parse('2026-09-07'), LocalDate::parse('2026-09-20'), $this->today);

        $this->assertSame(['done' => 2, 'total' => 2, 'rate' => 100.0], $r);
    }

    public function test_closed_week_below_target_counts_as_missed(): void
    {
        $habit = $this->weekly(3);
        $this->log($habit, ['2026-09-14', '2026-09-16']); // 2/3

        $r = $this->stats->completionRate($habit, LocalDate::parse('2026-09-14'), LocalDate::parse('2026-09-20'), $this->today);

        $this->assertSame(['done' => 0, 'total' => 1, 'rate' => 0.0], $r);
    }

    public function test_week_in_progress_below_target_is_not_evaluated(): void
    {
        $habit = $this->weekly(3);
        $this->log($habit, ['2026-09-21']); // semana en curso: 1/3

        $r = $this->stats->completionRate($habit, LocalDate::parse('2026-09-21'), $this->today, $this->today);

        $this->assertSame(0, $r['total']);
        $this->assertNull($r['rate']);
    }

    public function test_week_in_progress_that_met_target_counts(): void
    {
        $habit = $this->weekly(2);
        $this->log($habit, ['2026-09-21', '2026-09-23']);

        $r = $this->stats->completionRate($habit, LocalDate::parse('2026-09-21'), $this->today, $this->today);

        $this->assertSame(['done' => 1, 'total' => 1, 'rate' => 100.0], $r);
    }

    public function test_weekly_rate_last_7_days_spans_two_iso_weeks(): void
    {
        $habit = $this->weekly(2);
        // Últimos 7 días (18..24) tocan la semana 14..20 (cerrada, cumplida)
        // y la 21..27 (en curso, 1/2 => no evaluada).
        $this->log($habit, ['2026-09-15', '2026-09-17', '2026-09-22']);

        $r = $this->stats->lastDaysRate($habit, 7, $this->today);

        $this->assertSame(['done' => 1, 'total' => 1, 'rate' => 100.0], $r);
    }

    public function test_weekly_current_streak_counts_weeks_and_skips_unfinished_current_week(): void
    {
        $habit = $this->weekly(2);
        $this->log($habit, [
            '2026-08-31', '2026-09-01', // cumple
            '2026-09-07', '2026-09-12', // cumple
            '2026-09-14', '2026-09-20', // cumple
            '2026-09-22',               // semana en curso 1/2
        ]);

        $this->assertSame(3, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_weekly_current_streak_includes_current_week_when_met(): void
    {
        $habit = $this->weekly(2);
        $this->log($habit, ['2026-09-14', '2026-09-15', '2026-09-21', '2026-09-24']);

        $this->assertSame(2, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_weekly_streak_broken_by_missed_week(): void
    {
        $habit = $this->weekly(2);
        $this->log($habit, ['2026-08-31', '2026-09-01', '2026-09-07', '2026-09-14', '2026-09-15']);

        // Semana 07..13 solo tuvo 1 -> rompe.
        $this->assertSame(1, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_weekly_best_streak(): void
    {
        $habit = $this->weekly(1);
        $this->log($habit, ['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-09-14']);

        $this->assertSame(4, $this->stats->bestStreak($habit, $this->today));
        $this->assertSame(1, $this->stats->currentStreak($habit, $this->today));
    }

    public function test_partial_first_week_is_not_penalized(): void
    {
        // Creado el jueves 17: la semana 14..20 es parcial.
        $habit = $this->weekly(3, start: '2026-09-17');
        $this->log($habit, ['2026-09-18']);

        $r = $this->stats->completionRate($habit, LocalDate::parse('2026-09-14'), $this->today, $this->today);

        $this->assertSame(0, $r['total']);
    }

    /* ---------------- Día de la semana ---------------- */

    public function test_weekday_breakdown_uses_only_daily_habits(): void
    {
        $daily = $this->daily();
        $weekly = $this->weekly(1);
        // Rango lun 14 .. dom 20. Daily hecho lun, mar, mié. Weekly hecho sáb.
        $this->log($daily, ['2026-09-14', '2026-09-15', '2026-09-16']);
        $this->log($weekly, ['2026-09-19']);

        $r = $this->stats->weekdayBreakdown([$daily, $weekly], LocalDate::parse('2026-09-14'), LocalDate::parse('2026-09-20'), $this->today);

        $this->assertSame(100.0, $r[1]['rate']);
        $this->assertSame(100.0, $r[3]['rate']);
        $this->assertSame(0.0, $r[4]['rate']);
        $this->assertSame(['done' => 0, 'total' => 1, 'rate' => 0.0], $r[6]); // el semanal no suma
    }

    public function test_weekday_breakdown_accumulates_multiple_weeks(): void
    {
        $habit = $this->daily();
        // Lunes 07, 14, 21: hecho 07 y 21.
        $this->log($habit, ['2026-09-07', '2026-09-21']);

        $r = $this->stats->weekdayBreakdown([$habit], LocalDate::parse('2026-09-07'), $this->today, $this->today);

        $this->assertSame(['done' => 2, 'total' => 3, 'rate' => 66.7], $r[1]);
        // Jueves: 10, 17 (hoy 24 sin marcar no cuenta)
        $this->assertSame(2, $r[4]['total']);
    }

    /* ---------------- Heatmap ---------------- */

    public function test_heatmap_rates_per_day(): void
    {
        $a = $this->daily();
        $b = $this->daily(start: '2026-09-23');
        $this->log($a, ['2026-09-22', '2026-09-23']);
        $this->log($b, ['2026-09-24']);

        $map = collect($this->stats->heatmap([$a, $b], LocalDate::parse('2026-09-22'), LocalDate::parse('2026-09-25'), $this->today))->keyBy('date');

        $this->assertSame(100.0, $map['2026-09-22']['rate']); // solo A existía
        $this->assertSame(50.0, $map['2026-09-23']['rate']);  // A sí, B no
        $this->assertSame(100.0, $map['2026-09-24']['rate']); // hoy: A sin marcar no cuenta
        $this->assertTrue($map['2026-09-25']['future']);
        $this->assertNull($map['2026-09-25']['rate']);
    }

    /* ---------------- Días sin registro ---------------- */

    public function test_days_since_last_activity_is_null_when_never_logged(): void
    {
        $this->daily();

        $this->assertNull($this->stats->daysSinceLastActivity($this->user, $this->today));
    }

    public function test_days_since_last_activity_counts_from_last_completed_log(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-19']);
        $this->log($habit, ['2026-09-22'], completed: false); // no cuenta como actividad

        $this->assertSame(5, $this->stats->daysSinceLastActivity($this->user, $this->today));
    }

    public function test_days_since_last_activity_considers_priorities(): void
    {
        $habit = $this->daily();
        $this->log($habit, ['2026-09-10']);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-22', 'completed' => false]);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-23', 'position' => 1, 'completed' => null]);

        $this->assertSame(2, $this->stats->daysSinceLastActivity($this->user, $this->today));
    }

    public function test_days_since_last_activity_is_zero_when_logged_today(): void
    {
        $this->log($this->daily(), ['2026-09-24']);

        $this->assertSame(0, $this->stats->daysSinceLastActivity($this->user, $this->today));
    }

    public function test_days_since_last_activity_ignores_other_users(): void
    {
        $other = Habit::factory()->startingOn('2026-09-01')->create();
        $this->log($other, ['2026-09-24']);

        $this->assertNull($this->stats->daysSinceLastActivity($this->user, $this->today));
    }

    /* ---------------- Agregados ---------------- */

    public function test_overall_rate_sums_periods(): void
    {
        $a = $this->daily();
        $b = $this->daily();
        $this->log($a, ['2026-09-21', '2026-09-22', '2026-09-23']);
        $this->log($b, ['2026-09-21']);

        $r = $this->stats->overallRate([$a, $b], LocalDate::parse('2026-09-21'), $this->today, $this->today);

        $this->assertSame(['done' => 4, 'total' => 6, 'rate' => 66.7], $r);
    }

    public function test_priority_summary(): void
    {
        $f = fn ($date, $pos, $c) => DailyPriority::factory()->for($this->user)->create(['date' => $date, 'position' => $pos, 'completed' => $c]);
        $f('2026-09-22', 1, true);
        $f('2026-09-22', 2, false);
        $f('2026-09-23', 1, true);
        $f('2026-09-24', 1, null);
        $f('2026-09-01', 1, true); // fuera de rango

        $r = $this->stats->prioritySummary($this->user, LocalDate::parse('2026-09-18'), $this->today);

        $this->assertSame(2, $r['completed']);
        $this->assertSame(1, $r['not_completed']);
        $this->assertSame(1, $r['unmarked']);
        $this->assertSame(66.7, $r['rate']);
    }
}
