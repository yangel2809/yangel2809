<?php

namespace Tests\Feature;

use App\Models\DailyPriority;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 22:30:00', 'America/Caracas'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function save(string $date, array $texts)
    {
        return $this->actingAs($this->user)->put(route('priorities.update'), ['date' => $date, 'texts' => $texts]);
    }

    private function texts(string $date): array
    {
        return DailyPriority::where('date', $date)->orderBy('position')->pluck('text', 'position')->all();
    }

    public function test_edit_defaults_to_tomorrow_and_can_switch_to_today(): void
    {
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-25', 'text' => 'Plan de mañana']);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-24', 'text' => 'Plan de hoy']);

        $this->actingAs($this->user)->get(route('priorities.edit'))
            ->assertOk()->assertSee('viernes 25 de septiembre')->assertSee('Plan de mañana')->assertDontSee('Plan de hoy');

        $this->actingAs($this->user)->get(route('priorities.edit', ['para' => 'hoy']))
            ->assertOk()->assertSee('Plan de hoy');
    }

    public function test_saves_up_to_three_compacting_blanks(): void
    {
        $this->save('2026-09-25', ['', 'Llamar al cliente', '  Gimnasio  '])->assertSessionHasNoErrors();

        $this->assertSame([1 => 'Llamar al cliente', 2 => 'Gimnasio'], $this->texts('2026-09-25'));
    }

    public function test_more_than_three_is_rejected(): void
    {
        $this->save('2026-09-25', ['a', 'b', 'c', 'd'])->assertSessionHasErrors('texts');
        $this->assertSame(0, DailyPriority::count());
    }

    public function test_only_today_or_tomorrow(): void
    {
        $this->save('2026-09-26', ['x'])->assertSessionHasErrors('date');
        $this->save('2026-09-23', ['x'])->assertSessionHasErrors('date');
    }

    public function test_resaving_keeps_status_of_unchanged_texts(): void
    {
        $this->save('2026-09-24', ['Uno', 'Dos']);
        DailyPriority::where('text', 'Uno')->update(['completed' => true]);

        $this->save('2026-09-24', ['Dos', 'Uno', 'Tres']);

        $this->assertTrue(DailyPriority::where('text', 'Uno')->value('completed'));
        $this->assertNull(DailyPriority::where('text', 'Tres')->value('completed'));
        $this->assertSame([1 => 'Dos', 2 => 'Uno', 3 => 'Tres'], $this->texts('2026-09-24'));
    }

    public function test_saving_empty_clears_the_day(): void
    {
        $this->save('2026-09-25', ['Algo']);
        $this->save('2026-09-25', ['', '', '']);

        $this->assertSame([], $this->texts('2026-09-25'));
    }

    public function test_mark_sets_completed_not_completed_and_clears(): void
    {
        $p = DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-24']);
        $url = route('priorities.mark', $p);

        $this->actingAs($this->user)->patchJson($url, ['completed' => true])->assertJson(['completed' => true]);
        $this->actingAs($this->user)->patchJson($url, ['completed' => false])->assertJson(['completed' => false]);
        $this->actingAs($this->user)->patchJson($url, ['completed' => null])->assertJson(['completed' => null]);
        $this->assertNull($p->fresh()->completed);
    }

    public function test_cannot_mark_old_or_future_priorities(): void
    {
        $old = DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-10']);
        $future = DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-25']);

        $this->actingAs($this->user)->patchJson(route('priorities.mark', $old), ['completed' => true])->assertUnprocessable();
        $this->actingAs($this->user)->patchJson(route('priorities.mark', $future), ['completed' => true])->assertUnprocessable();
    }

    public function test_cannot_mark_other_users_priority(): void
    {
        $p = DailyPriority::factory()->create(['date' => '2026-09-24']);

        $this->actingAs($this->user)->patchJson(route('priorities.mark', $p), ['completed' => true])->assertForbidden();
    }
}
