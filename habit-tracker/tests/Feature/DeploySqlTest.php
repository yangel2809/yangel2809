<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeploySqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_committed_schema_sql_matches_migrations(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'schema');
        Artisan::call('deploy:sql', ['--all' => true, '--output' => $tmp]);

        $this->assertFileEquals(
            base_path('deploy/sql/schema.sql'),
            $tmp,
            'deploy/sql/schema.sql está desactualizado: php artisan deploy:sql --all --output=deploy/sql/schema.sql'
        );
        unlink($tmp);
    }

    public function test_schema_sql_registers_every_migration(): void
    {
        $sql = file_get_contents(base_path('deploy/sql/schema.sql'));

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $this->assertStringContainsString("VALUES ('".basename($file, '.php')."', @batch)", $sql);
        }
    }

    public function test_pending_mode_reports_nothing_when_up_to_date(): void
    {
        $this->artisan('deploy:sql')->expectsOutput('No hay migraciones pendientes.')->assertSuccessful();
    }
}
