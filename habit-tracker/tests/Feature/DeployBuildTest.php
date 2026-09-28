<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployBuildTest extends TestCase
{
    public function test_fails_without_production_env_file(): void
    {
        $this->artisan('deploy:build', ['--env-file' => '.env.no-existe'])
            ->assertFailed();
    }

    public function test_front_controller_template_points_to_laravel_dir(): void
    {
        $index = file_get_contents(base_path('deploy/infinityfree/index.php'));

        $this->assertStringContainsString("__DIR__.'/laravel'", $index);
        $this->assertStringContainsString('usePublicPath(__DIR__)', $index);
    }

    public function test_htaccess_blocks_core_and_dotfiles(): void
    {
        $htaccess = file_get_contents(base_path('deploy/infinityfree/htaccess'));

        $this->assertStringContainsString('RewriteRule ^laravel(/|$) - [F,L]', $htaccess);
        $this->assertMatchesRegularExpression('/RewriteRule \(\^\|\/\)\\\\\./', $htaccess);
        $this->assertStringContainsString('Require all denied', file_get_contents(base_path('deploy/infinityfree/laravel.htaccess')));
    }
}
