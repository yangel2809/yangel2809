<?php

namespace Tests\Feature;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\ReportGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 22:00:00', 'America/Caracas'));
        $this->user = User::factory()->create(['name' => 'Yangel']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_report_page_defaults_to_7_days(): void
    {
        $this->actingAs($this->user)->get(route('report'))
            ->assertOk()
            ->assertSee('(7 días)')
            ->assertSee(ReportGenerator::QUESTION);
    }

    public function test_report_page_accepts_14_and_30_and_ignores_others(): void
    {
        $this->actingAs($this->user)->get(route('report', ['dias' => 30]))->assertSee('(30 días)');
        $this->actingAs($this->user)->get(route('report', ['dias' => 999]))->assertSee('(7 días)');
    }

    public function test_report_download_is_markdown_attachment(): void
    {
        $res = $this->actingAs($this->user)->get(route('report.download', ['dias' => 14]));

        $res->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="informe-habitos-2026-09-24-14d.md"');
        $this->assertStringContainsString('(14 días)', $res->getContent());
    }

    public function test_export_contains_all_own_data(): void
    {
        $habit = Habit::factory()->for($this->user)->weekly(3)->startingOn('2026-09-01')->create(['name' => 'Gimnasio']);
        HabitLog::factory()->for($habit)->create(['date' => '2026-09-22', 'note' => 'Pierna']);
        Habit::factory()->for($this->user)->archived()->create(['name' => 'Viejo']);
        DailyPriority::factory()->for($this->user)->create(['date' => '2026-09-24', 'text' => 'Deploy', 'completed' => true]);
        $other = Habit::factory()->create(['name' => 'Ajeno']);
        HabitLog::factory()->for($other)->create();

        $res = $this->actingAs($this->user)->get(route('export'));

        $res->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="habitos-respaldo-2026-09-24.json"');
        $json = $res->json();
        $this->assertSame(1, $json['format_version']);
        $this->assertSame('America/Caracas', $json['timezone']);
        $this->assertSame(['Gimnasio', 'Viejo'], array_column($json['habits'], 'name'));
        $this->assertSame([['date' => '2026-09-22', 'completed' => true, 'note' => 'Pierna']], $json['habits'][0]['logs']);
        $this->assertSame(3, $json['habits'][0]['weekly_target']);
        $this->assertNotNull($json['habits'][1]['archived_at']);
        $this->assertSame([['date' => '2026-09-24', 'position' => 1, 'text' => 'Deploy', 'completed' => true]], $json['priorities']);
        $this->assertStringNotContainsString('Ajeno', $res->getContent());
        $this->assertArrayNotHasKey('password', $json['user']);
    }

    public function test_guest_cannot_export(): void
    {
        $this->get(route('export'))->assertRedirect('/login');
        $this->get(route('report'))->assertRedirect('/login');
    }
}
