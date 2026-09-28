<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_installable_and_icons_exist(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $m['display']);
        $this->assertSame('./', $m['start_url']);
        $sizes = [];
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']));
            $sizes[] = $icon['sizes'].'/'.$icon['purpose'];
        }
        $this->assertContains('192x192/any', $sizes);
        $this->assertContains('512x512/any', $sizes);
        $this->assertContains('512x512/maskable', $sizes);
    }

    public function test_icons_are_not_under_apache_icons_alias(): void
    {
        // Apache trae "Alias /icons/" por defecto: esa carpeta nunca llega a la app.
        $this->assertDirectoryDoesNotExist(public_path('icons'));
    }

    public function test_layouts_link_manifest_and_service_worker(): void
    {
        $this->get('/login')->assertSee('manifest.webmanifest')->assertSee('sw.js');
        $this->actingAs(User::factory()->create())->get('/')->assertSee('manifest.webmanifest')->assertSee('apple-touch-icon');
    }

    public function test_service_worker_never_caches_navigations(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("req.mode === 'navigate'", $sw);
        $this->assertStringContainsString('fetch(req).catch(() => caches.match(OFFLINE))', $sw);
        $this->assertFileExists(public_path('offline.html'));
    }
}
