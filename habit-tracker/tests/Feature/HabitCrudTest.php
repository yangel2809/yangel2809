<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitCrudTest extends TestCase
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

    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Leer',
            'frequency_type' => 'daily',
            'weekly_target' => 4,
            'color' => '#3B82F6',
            'start_date' => '2026-09-24',
        ], $override);
    }

    public function test_create_form_defaults_start_date_to_local_today(): void
    {
        $this->actingAs($this->user)->get(route('habits.create'))->assertOk()->assertSee('value="2026-09-24"', false);
    }

    public function test_creates_daily_habit_ignoring_weekly_target(): void
    {
        $this->actingAs($this->user)->post(route('habits.store'), $this->valid())->assertRedirect(route('habits.index'));

        $habit = Habit::sole();
        $this->assertSame($this->user->id, $habit->user_id);
        $this->assertNull($habit->weekly_target);
        $this->assertSame('#3b82f6', $habit->color);
        $this->assertSame('2026-09-24', $habit->start_date);
    }

    public function test_creates_weekly_habit(): void
    {
        $this->actingAs($this->user)->post(route('habits.store'), $this->valid(['frequency_type' => 'weekly', 'weekly_target' => 3]));

        $this->assertSame(3, Habit::sole()->weekly_target);
    }

    public function test_weekly_requires_target_between_1_and_6(): void
    {
        $this->actingAs($this->user)->post(route('habits.store'), $this->valid(['frequency_type' => 'weekly', 'weekly_target' => null]))
            ->assertSessionHasErrors('weekly_target');
        $this->actingAs($this->user)->post(route('habits.store'), $this->valid(['frequency_type' => 'weekly', 'weekly_target' => 7]))
            ->assertSessionHasErrors('weekly_target');
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $this->actingAs($this->user)->post(route('habits.store'), $this->valid(['name' => '', 'color' => 'rojo']))
            ->assertSessionHasErrors(['name' => 'Nombre es obligatorio.', 'color']);
    }

    public function test_updates_habit(): void
    {
        $habit = Habit::factory()->for($this->user)->create();

        $this->actingAs($this->user)->put(route('habits.update', $habit), $this->valid(['name' => 'Leer 30 min', 'frequency_type' => 'weekly', 'weekly_target' => 5]));

        $habit->refresh();
        $this->assertSame('Leer 30 min', $habit->name);
        $this->assertSame(5, $habit->weekly_target);
    }

    public function test_archive_and_restore(): void
    {
        $habit = Habit::factory()->for($this->user)->create();

        $this->actingAs($this->user)->patch(route('habits.archive', $habit));
        $this->assertTrue($habit->fresh()->isArchived());

        $this->actingAs($this->user)->patch(route('habits.unarchive', $habit));
        $this->assertFalse($habit->fresh()->isArchived());
    }

    public function test_destroy_removes_logs(): void
    {
        $habit = Habit::factory()->for($this->user)->create();
        HabitLog::factory()->for($habit)->create();

        $this->actingAs($this->user)->delete(route('habits.destroy', $habit))->assertRedirect(route('habits.index'));

        $this->assertSame(0, Habit::count());
        $this->assertSame(0, HabitLog::count());
    }

    public function test_cannot_manage_other_users_habits(): void
    {
        $other = Habit::factory()->create();

        $this->actingAs($this->user)->get(route('habits.edit', $other))->assertForbidden();
        $this->actingAs($this->user)->put(route('habits.update', $other), $this->valid())->assertForbidden();
        $this->actingAs($this->user)->patch(route('habits.archive', $other))->assertForbidden();
        $this->actingAs($this->user)->delete(route('habits.destroy', $other))->assertForbidden();
        $this->assertSame(1, Habit::count());
    }

    public function test_index_separates_archived(): void
    {
        Habit::factory()->for($this->user)->create(['name' => 'Activo']);
        Habit::factory()->for($this->user)->archived()->create(['name' => 'Viejo']);

        $this->actingAs($this->user)->get(route('habits.index'))->assertOk()->assertSeeInOrder(['Activo', 'Archivados (1)', 'Viejo']);
    }
}
