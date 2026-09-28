<?php

namespace Tests\Unit;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\FeedbackService;
use App\Services\HabitStatsService;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Hoy: jueves 2026-09-24. Semana en curso 21..27, anterior 14..20. */
class FeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 20:00:00', 'America/Caracas'));
        $this->user = User::factory()->create();
        $this->today = LocalDate::today();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function habit(string $name, array $attrs = []): Habit
    {
        return Habit::factory()->for($this->user)->startingOn('2026-08-01')->create(['name' => $name] + $attrs);
    }

    private function log(Habit $h, iterable $dates): void
    {
        foreach ($dates as $d) {
            HabitLog::factory()->for($h)->create(['date' => $d]);
        }
    }

    /** @return array<string, array{key: string, tone: string, text: string}> */
    private function messages(): array
    {
        $msgs = (new FeedbackService(new HabitStatsService()))->messages($this->user, null, $this->today);

        return collect($msgs)->keyBy(fn ($m) => explode(':', $m['key'])[0])->all();
    }

    private function range(string $from, string $to): array
    {
        $out = [];
        for ($d = LocalDate::parse($from); $d->lte(LocalDate::parse($to)); $d = $d->addDay()) {
            $out[] = $d->format('Y-m-d');
        }

        return $out;
    }

    public function test_no_habits_no_messages(): void
    {
        $this->assertSame([], (new FeedbackService(new HabitStatsService()))->messages($this->user, null, $this->today));
    }

    public function test_never_logged(): void
    {
        $this->habit('Leer');

        $this->assertSame('Aún no has registrado nada. Empieza marcando un hábito hoy.', $this->messages()['inactive']['text']);
    }

    public function test_days_without_logging(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, ['2026-09-20']);

        $m = $this->messages()['inactive'];
        $this->assertSame('warn', $m['tone']);
        $this->assertStringStartsWith('No has registrado nada en 4 días.', $m['text']);
    }

    public function test_no_inactivity_message_when_logged_yesterday(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, ['2026-09-23']);

        $this->assertArrayNotHasKey('inactive', $this->messages());
    }

    public function test_current_streak_message(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, $this->range('2026-09-01', '2026-09-10')); // mejor: 10
        $this->log($h, $this->range('2026-09-19', '2026-09-24')); // actual: 6

        $this->assertSame('Llevas 6 días seguidos con Leer.', $this->messages()['streak']['text']);
    }

    public function test_best_streak_is_called_out(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, $this->range('2026-09-18', '2026-09-24'));

        $this->assertSame('Llevas 7 días seguidos con Leer. Es tu mejor racha.', $this->messages()['streak']['text']);
    }

    public function test_weekly_streak_message(): void
    {
        $h = $this->habit('Gimnasio', ['frequency_type' => 'weekly', 'weekly_target' => 2]);
        $this->log($h, ['2026-09-01', '2026-09-03', '2026-09-08', '2026-09-10', '2026-09-15', '2026-09-17']);

        $this->assertStringStartsWith('Llevas 3 semanas seguidas cumpliendo Gimnasio.', $this->messages()['streak']['text']);
    }

    public function test_weakest_weekday(): void
    {
        $h = $this->habit('Leer');
        // Últimos 30 días (26 ago .. 24 sep): todo hecho excepto los lunes.
        $dates = array_filter($this->range('2026-08-26', '2026-09-23'), fn ($d) => LocalDate::parse($d)->isoWeekday() !== 1);
        $this->log($h, $dates);

        $m = $this->messages()['weekday'];
        $this->assertStringStartsWith('Tu día más débil es el lunes: cumples solo el 0%', $m['text']);
    }

    public function test_no_weakest_weekday_when_uniform(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, $this->range('2026-08-26', '2026-09-23'));

        $this->assertArrayNotHasKey('weekday', $this->messages());
    }

    public function test_week_comparison_worse(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, $this->range('2026-09-14', '2026-09-20')); // semana pasada 100%
        $this->log($h, ['2026-09-21']);                          // esta: 1 de 3 días cerrados (hoy sin marcar)

        $m = $this->messages()['week'];
        $this->assertSame('warn', $m['tone']);
        $this->assertSame('Esta semana vas en 33% vs 100% la semana pasada. Quedan 3 días para recuperar.', $m['text']);
    }

    public function test_week_comparison_better(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, ['2026-09-14']);
        $this->log($h, $this->range('2026-09-21', '2026-09-24'));

        $m = $this->messages()['week'];
        $this->assertSame('good', $m['tone']);
        $this->assertStringContainsString('vas en 100% vs 14%', $m['text']);
    }

    public function test_weekly_goal_out_of_reach(): void
    {
        // Jueves, 0/5: quedan 4 días (jue..dom) y faltan 5.
        $this->habit('Gimnasio', ['frequency_type' => 'weekly', 'weekly_target' => 5]);
        $this->log(Habit::first(), ['2026-09-10']);

        $this->assertStringStartsWith('Esta semana ya no llegas a la meta de Gimnasio (0/5).', $this->messages()['weekly']['text']);
    }

    public function test_weekly_goal_tight(): void
    {
        $h = $this->habit('Gimnasio', ['frequency_type' => 'weekly', 'weekly_target' => 4]);
        $this->log($h, ['2026-09-21']); // 1/4, faltan 3, quedan 4 días

        $this->assertSame('Te faltan 3 de Gimnasio y quedan 4 días.', $this->messages()['weekly']['text']);
    }

    public function test_struggling_daily_habit(): void
    {
        $ok = $this->habit('Agua');
        $bad = $this->habit('Meditar');
        $this->log($ok, $this->range('2026-09-18', '2026-09-24'));
        $this->log($bad, ['2026-09-19']);

        $this->assertStringStartsWith('Meditar va en 17% los últimos 7 días.', $this->messages()['struggling']['text']);
    }

    public function test_priorities_summary(): void
    {
        $this->log($this->habit('Leer'), ['2026-09-24']);
        foreach ([true, true, true, false] as $i => $c) {
            DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-2'.($i + 1), 'completed' => $c]);
        }

        $m = $this->messages()['priorities'];
        $this->assertSame('good', $m['tone']);
        $this->assertSame('Cumpliste 3 de 4 prioridades en los últimos 7 días.', $m['text']);
    }

    public function test_at_most_five_messages(): void
    {
        $a = $this->habit('A');
        $b = $this->habit('B');
        $this->habit('C', ['frequency_type' => 'weekly', 'weekly_target' => 6]);
        $this->log($a, $this->range('2026-09-14', '2026-09-24'));
        $this->log($b, ['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-22']);
        foreach (range(1, 4) as $i) {
            DailyPriority::factory()->for($this->user)->create(['date' => "2026-09-2{$i}", 'completed' => false]);
        }

        $msgs = (new FeedbackService(new HabitStatsService()))->messages($this->user, null, $this->today);
        $this->assertLessThanOrEqual(FeedbackService::MAX, count($msgs));
    }
}
