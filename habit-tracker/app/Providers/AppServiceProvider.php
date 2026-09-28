<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Índices sobre varchar(255) utf8mb4 superan 767 bytes en MySQL < 5.7
        // sin innodb_large_prefix. El hosting compartido no garantiza la versión.
        Schema::defaultStringLength(191);

        // En InfinityFree el SSL termina antes de PHP y $_SERVER['HTTPS'] puede
        // no llegar: sin esto Laravel genera URLs http:// (mixed content).
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
