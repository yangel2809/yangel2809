<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 20:00:00', 'America/Caracas'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get(route('progress'))
            ->assertOk()->assertSee('Crea un hábito');
    }

    public function test_renders_stats_feedback_and_only_own_habits(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->startingOn('2026-09-01')->create(['name' => 'Leer']);
        foreach (['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24'] as $d) {
            HabitLog::factory()->for($habit)->create(['date' => $d]);
        }
        Habit::factory()->startingOn('2026-09-01')->create(['name' => 'Ajeno']);
        Habit::factory()->for($user)->startingOn('2026-09-30')->create(['name' => 'Futuro']);

        $this->actingAs($user)->get(route('progress'))
            ->assertOk()
            ->assertSee('Leer')
            ->assertSee('Llevas 5 días seguidos con Leer.')
            ->assertSee('id="progress-data"', false)
            ->assertDontSee('Ajeno')
            ->assertDontSee('Futuro');
    }
}
