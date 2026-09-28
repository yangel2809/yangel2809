<?php

namespace Tests\Unit;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\HabitStatsService;
use App\Services\ReportGenerator;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Hoy: jueves 2026-09-24. Últimos 7 días: vie 18 .. jue 24. */
class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 22:00:00', 'America/Caracas'));
        $this->user = User::factory()->create();
        $this->today = LocalDate::today();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function report(int $days = 7): string
    {
        return (new ReportGenerator(new HabitStatsService()))->generate($this->user, $days, $this->today);
    }

    private function habit(string $name, array $attrs = []): Habit
    {
        return Habit::factory()->for($this->user)->startingOn('2026-08-01')->create(['name' => $name] + $attrs);
    }

    private function log(Habit $h, array $dates, bool $completed = true, ?string $note = null): void
    {
        foreach ($dates as $d) {
            HabitLog::factory()->for($h)->create(['date' => $d, 'completed' => $completed, 'note' => $note]);
        }
    }

    public function test_header_has_date_range_and_ends_with_question(): void
    {
        $md = $this->report(7);

        $this->assertStringStartsWith('# Informe de hábitos: 18 de septiembre de 2026 al 24 de septiembre de 2026 (7 días)', $md);
        $this->assertStringEndsWith("---\n\n".ReportGenerator::QUESTION."\n", $md);
        $this->assertStringContainsString('America/Caracas', $md);
    }

    public function test_range_changes_with_days(): void
    {
        $this->assertStringContainsString('11 de septiembre de 2026 al 24 de septiembre de 2026 (14 días)', $this->report(14));
        $this->assertStringContainsString('26 de agosto de 2026 al 24 de septiembre de 2026 (30 días)', $this->report(30));
    }

    public function test_rejects_unsupported_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->report(10);
    }

    public function test_habit_table_with_rate_and_streaks(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, ['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04']); // mejor 4
        $this->log($h, ['2026-09-18', '2026-09-22', '2026-09-23']);            // 3/6 (hoy sin marcar), actual 2

        $this->assertStringContainsString('| Leer | Diario | 50% (3/6 días) | 2 días | 4 días |', $this->report());
    }

    public function test_weekly_habit_uses_weeks(): void
    {
        $h = $this->habit('Gimnasio', ['frequency_type' => 'weekly', 'weekly_target' => 2]);
        $this->log($h, ['2026-09-14', '2026-09-16', '2026-09-22']);

        $md = $this->report();
        $this->assertStringContainsString('| Gimnasio | 2× por semana | 100% (1/1 semanas) | 1 semana | 1 semana |', $md);
        $this->assertStringContainsString('- Gimnasio: semana del lun 14 sep: 2/2 · semana del lun 21 sep: 1/2 (en curso)', $md);
    }

    public function test_weekday_pattern_only_daily_habits(): void
    {
        $h = $this->habit('Leer');
        $this->habit('Gimnasio', ['frequency_type' => 'weekly', 'weekly_target' => 2]);
        $this->log($h, ['2026-09-18', '2026-09-19', '2026-09-20', '2026-09-22', '2026-09-23']); // falla lunes 21

        $md = $this->report();
        $this->assertStringContainsString('- Hábitos activos: 2 (1 diario, 1 semanal)', $md);
        $this->assertStringContainsString('## Patrón por día de la semana (solo hábitos diarios)', $md);
        $this->assertStringContainsString('| Lunes | 0% (0/1) |', $md);
        $this->assertStringContainsString('| Viernes | 100% (1/1) |', $md);
        $this->assertStringContainsString('| Jueves | sin datos |', $md); // hoy sin marcar
        $this->assertStringContainsString('- Día más débil: lunes (0%).', $md);
    }

    public function test_priorities_listed_by_day_with_status(): void
    {
        $this->habit('Leer');
        $p = fn ($date, $pos, $text, $c) => DailyPriority::factory()->for($this->user)->create(compact('date', 'text') + ['position' => $pos, 'completed' => $c]);
        $p('2026-09-22', 1, 'Enviar cotización', true);
        $p('2026-09-22', 2, 'Revisar PR', false);
        $p('2026-09-24', 1, 'Llamar proveedor', null);
        $p('2026-09-10', 1, 'Fuera de rango', true);

        $md = $this->report();
        $this->assertStringContainsString("**mar 22 sep**\n- [x] Enviar cotización\n- [ ] Revisar PR (no cumplida)", $md);
        $this->assertStringContainsString("**jue 24 sep**\n- [ ] Llamar proveedor (sin marcar)", $md);
        $this->assertStringContainsString('- Prioridades: 1 cumplidas, 1 no cumplidas, 1 sin marcar', $md);
        $this->assertStringNotContainsString('Fuera de rango', $md);
    }

    public function test_notes_included_in_order_and_sanitized(): void
    {
        $a = $this->habit('Leer | libros');
        $b = $this->habit('Meditar');
        $this->log($b, ['2026-09-23'], false, 'No pude, reunión tarde');
        $this->log($a, ['2026-09-20'], true, "Capítulo 3\n\nbuen ritmo");
        $this->log($a, ['2026-09-01'], true, 'Nota vieja');

        $md = $this->report();
        $this->assertStringContainsString("## Mis notas\n\n- dom 20 sep · Leer | libros (hecho): Capítulo 3 buen ritmo\n- mié 23 sep · Meditar (no hecho): No pude, reunión tarde", $md);
        $this->assertStringContainsString('| Leer \| libros |', $md); // pipe escapado en la tabla
        $this->assertStringNotContainsString('Nota vieja', $md);
    }

    public function test_summary_counts_empty_days_and_previous_period(): void
    {
        $h = $this->habit('Leer');
        $this->log($h, ['2026-09-11', '2026-09-12', '2026-09-13', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17']); // anterior: 100%
        $this->log($h, ['2026-09-20', '2026-09-23']);

        $md = $this->report();
        $this->assertStringContainsString('- Cumplimiento global: 33% · periodo anterior (7 días): 100%', $md);
        $this->assertStringContainsString('- Días sin ningún registro: 5 de 7', $md);
    }

    public function test_excludes_archived_other_users_and_future_habits(): void
    {
        $this->habit('Visible');
        $this->habit('Archivado', ['archived_at' => now()]);
        Habit::factory()->for($this->user)->startingOn('2026-09-30')->create(['name' => 'Futuro']);
        Habit::factory()->startingOn('2026-09-01')->create(['name' => 'Ajeno']);

        $md = $this->report();
        $this->assertStringContainsString('| Visible |', $md);
        foreach (['Archivado', 'Futuro', 'Ajeno'] as $name) {
            $this->assertStringNotContainsString($name, $md);
        }
    }

    public function test_habit_started_mid_range_is_flagged(): void
    {
        Habit::factory()->for($this->user)->startingOn('2026-09-21')->create(['name' => 'Nuevo']);

        $this->assertStringContainsString('- Nuevo empezó el lun 21 sep; antes no cuenta.', $this->report());
    }

    public function test_empty_account_still_produces_valid_report(): void
    {
        $md = $this->report();

        $this->assertStringContainsString('- No tengo hábitos activos en este periodo.', $md);
        $this->assertStringContainsString('No planeé prioridades en este periodo.', $md);
        $this->assertStringEndsWith(ReportGenerator::QUESTION."\n", $md);
    }
}
