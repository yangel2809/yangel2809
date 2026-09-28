<?php

namespace Tests\Feature;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Hoy: jueves 2026-09-24 21:00 en Caracas (= 25 01:00 UTC). */
class TodayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 21:00:00', 'America/Caracas'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function habit(array $attrs = []): Habit
    {
        return Habit::factory()->for($this->user)->startingOn('2026-09-01')->create($attrs);
    }

    private function putLog(Habit $habit, array $data)
    {
        return $this->actingAs($this->user)->putJson(route('logs.update', $habit), $data);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_today_lists_active_started_habits_and_todays_priorities(): void
    {
        $this->habit(['name' => 'Leer']);
        $this->habit(['name' => 'Archivado', 'archived_at' => now()]);
        Habit::factory()->for($this->user)->startingOn('2026-09-30')->create(['name' => 'Futuro']);
        Habit::factory()->startingOn('2026-09-01')->create(['name' => 'De otro']);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-24', 'text' => 'Enviar propuesta']);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-25', 'text' => 'Es de mañana']);

        $this->actingAs($this->user)->get('/')
            ->assertOk()
            ->assertSee('Leer')
            ->assertSee('Enviar propuesta')
            ->assertDontSee('Archivado')
            ->assertDontSee('Futuro')
            ->assertDontSee('De otro')
            ->assertDontSee('Es de mañana');
    }

    public function test_today_uses_caracas_date(): void
    {
        // En UTC ya es 25; la prioridad del 24 debe verse.
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-24', 'text' => 'Del 24']);

        $this->actingAs($this->user)->get('/')->assertSee('Del 24');
    }

    public function test_priorities_planned_yesterday_show_up_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 22:00:00', 'America/Caracas'));
        $this->actingAs($this->user)->put(route('priorities.update'), [
            'date' => '2026-09-24',
            'texts' => ['Terminar informe', '', ''],
        ])->assertRedirect();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 07:00:00', 'America/Caracas'));
        $this->actingAs($this->user)->get('/')->assertSee('Terminar informe');
    }

    public function test_past_day_within_window_is_viewable_and_outside_redirects(): void
    {
        $this->actingAs($this->user)->get('/?fecha=2026-09-18')->assertOk()->assertSee('Estás editando un día pasado');
        $this->actingAs($this->user)->get('/?fecha=2026-09-17')->assertRedirect(route('today'));
        $this->actingAs($this->user)->get('/?fecha=2026-09-25')->assertRedirect(route('today'));
        $this->actingAs($this->user)->get('/?fecha=basura')->assertRedirect(route('today'));
    }

    /* ---------------- Registro de hábitos ---------------- */

    public function test_marking_done_creates_log_and_returns_streak(): void
    {
        $habit = $this->habit();
        HabitLog::factory()->for($habit)->create(['date' => '2026-09-23']);

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])
            ->assertOk()
            ->assertJson(['done' => true, 'streak' => 2]);

        $this->assertDatabaseHas('habit_logs', ['habit_id' => $habit->id, 'date' => '2026-09-24', 'completed' => 1]);
    }

    public function test_setting_state_is_idempotent(): void
    {
        $habit = $this->habit();

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])->assertJson(['done' => true]);
        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])->assertJson(['done' => true]);

        $this->assertSame(1, HabitLog::count());
    }

    public function test_unmarking_keeps_note(): void
    {
        $habit = $this->habit();
        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true]);
        $this->putLog($habit, ['date' => '2026-09-24', 'note' => 'Solo 10 minutos']);

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => false])
            ->assertJson(['done' => false, 'note' => 'Solo 10 minutos']);
    }

    public function test_note_without_log_creates_not_completed_entry(): void
    {
        $habit = $this->habit();

        $this->putLog($habit, ['date' => '2026-09-24', 'note' => '  Me dolía la rodilla  '])
            ->assertJson(['done' => false, 'note' => 'Me dolía la rodilla']);

        $this->putLog($habit, ['date' => '2026-09-24', 'note' => ''])->assertJson(['note' => null]);
    }

    public function test_weekly_habit_returns_week_count(): void
    {
        $habit = $this->habit(['frequency_type' => 'weekly', 'weekly_target' => 3]);
        HabitLog::factory()->for($habit)->create(['date' => '2026-09-21']);

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])
            ->assertJson(['week_count' => 2, 'target' => 3, 'streak' => 0]);
    }

    public function test_can_mark_yesterday_after_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 00:40:00', 'America/Caracas'));
        $habit = $this->habit();

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])->assertOk();
    }

    public function test_rejects_dates_outside_window_or_before_start(): void
    {
        $habit = $this->habit();
        $this->putLog($habit, ['date' => '2026-09-17', 'completed' => true])->assertUnprocessable();
        $this->putLog($habit, ['date' => '2026-09-25', 'completed' => true])->assertUnprocessable();
        $this->putLog($habit, ['date' => '2026-02-30', 'completed' => true])->assertUnprocessable();

        $late = Habit::factory()->for($this->user)->startingOn('2026-09-24')->create();
        $this->putLog($late, ['date' => '2026-09-23', 'completed' => true])->assertUnprocessable();
    }

    public function test_rejects_archived_habit(): void
    {
        $habit = $this->habit(['archived_at' => now()]);

        $this->putLog($habit, ['date' => '2026-09-24', 'completed' => true])->assertUnprocessable();
    }

    public function test_cannot_touch_other_users_habit(): void
    {
        $other = Habit::factory()->startingOn('2026-09-01')->create();

        $this->putLog($other, ['date' => '2026-09-24', 'completed' => true])->assertForbidden();
        $this->assertSame(0, HabitLog::count());
    }
}
