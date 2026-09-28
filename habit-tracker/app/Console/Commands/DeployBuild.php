<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

/**
 * Arma una carpeta htdocs/ lista para subir por FTP a un hosting compartido
 * con open_basedir (InfinityFree): sin symlinks, sin artisan en el servidor.
 *
 *   htdocs/                 <- contenido de public/ + index.php adaptado
 *   htdocs/laravel/         <- app, config, vendor (--no-dev), storage, .env
 */
class DeployBuild extends Command
{
    protected $signature = 'deploy:build
        {--env-file=.env.production : .env de producción (relativo a la raíz del proyecto)}
        {--out=build/infinityfree : Carpeta de salida}
        {--composer=composer : Ejecutable de Composer}';

    protected $description = 'Genera build/infinityfree/htdocs para subir por FTP';

    /** Directorios de la app que van a htdocs/laravel. */
    private const CORE_DIRS = ['app', 'bootstrap', 'config', 'database', 'lang', 'resources/views', 'routes'];

    private const CORE_FILES = ['artisan', 'composer.json', 'composer.lock'];

    public function handle(Filesystem $fs): int
    {
        $root = base_path();
        $template = $root.'/deploy/infinityfree';
        $envFile = $root.'/'.$this->option('env-file');
        $out = $root.'/'.trim($this->option('out'), '/');
        $htdocs = $out.'/htdocs';
        $core = $htdocs.'/laravel';

        if (! $this->preflight($fs, $root, $envFile)) {
            return self::FAILURE;
        }

        $this->components->task('Limpiando '.$this->relative($out), fn () => $fs->deleteDirectory($out) || true);
        $fs->ensureDirectoryExists($core);

        $this->components->task('Copiando public/ a htdocs/', function () use ($fs, $root, $htdocs, $template) {
            foreach ($fs->files($root.'/public', true) as $file) {
                if (! in_array($file->getFilename(), ['index.php', '.htaccess', 'hot'], true)) {
                    $fs->copy($file->getPathname(), $htdocs.'/'.$file->getFilename());
                }
            }
            foreach ($fs->directories($root.'/public') as $dir) {
                if (basename($dir) !== 'storage') {
                    $fs->copyDirectory($dir, $htdocs.'/'.basename($dir));
                }
            }
            $fs->copy($template.'/index.php', $htdocs.'/index.php');
            $fs->copy($template.'/htaccess', $htdocs.'/.htaccess');
        });

        $this->components->task('Copiando código a htdocs/laravel/', function () use ($fs, $root, $core, $template, $envFile) {
            foreach (self::CORE_DIRS as $dir) {
                if ($fs->isDirectory($root.'/'.$dir)) {
                    $fs->copyDirectory($root.'/'.$dir, $core.'/'.$dir);
                }
            }
            foreach (self::CORE_FILES as $file) {
                $fs->copy($root.'/'.$file, $core.'/'.$file);
            }
            // Caché local (rutas absolutas de tu máquina, paquetes dev): fuera.
            foreach ($fs->glob($core.'/bootstrap/cache/*.php') as $cached) {
                $fs->delete($cached);
            }
            $this->copyStorageSkeleton($fs, $root.'/storage', $core.'/storage');
            $fs->copy($envFile, $core.'/.env');
            $fs->copy($template.'/laravel.htaccess', $core.'/.htaccess');
        });

        $this->components->info('Instalando dependencias de producción (composer install --no-dev)...');
        $result = Process::path($core)
            ->timeout(900)
            ->env(['COMPOSER_VENDOR_DIR' => 'vendor'])
            ->run(
                $this->option('composer').' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress',
                fn ($type, $line) => $this->output->write($line)
            );

        if ($result->failed()) {
            $this->components->error('composer install falló.');

            return self::FAILURE;
        }

        $count = iterator_count(Finder::create()->in($htdocs)->ignoreDotFiles(false)->ignoreVCS(false));
        $this->newLine();
        $this->components->twoColumnDetail('Carpeta lista', $this->relative($htdocs));
        $this->components->twoColumnDetail('Archivos + carpetas (inodes)', number_format($count).' / 30.000');
        if ($count > 20000) {
            $this->components->warn('Te acercas al límite de inodes de InfinityFree.');
        }
        $this->components->info('Sube el CONTENIDO de htdocs/ a htdocs/ del hosting (ver README).');

        return self::SUCCESS;
    }

    private function preflight(Filesystem $fs, string $root, string $envFile): bool
    {
        $ok = true;

        if (! $fs->exists($root.'/public/build/manifest.json')) {
            $this->components->error('Falta public/build: corre "npm run build" antes.');
            $ok = false;
        }
        if ($fs->exists($root.'/public/hot')) {
            $this->components->error('Existe public/hot (npm run dev activo): detén Vite y corre "npm run build".');
            $ok = false;
        }
        if (! $fs->exists($envFile)) {
            $this->components->error("No existe {$this->relative($envFile)}. Cópialo desde deploy/infinityfree/env.example.");

            return false;
        }

        $env = $fs->get($envFile);
        if (! preg_match('/^APP_KEY=base64:\S+/m', $env)) {
            $this->components->error('APP_KEY vacío en '.$this->relative($envFile).'. Genera uno con: php artisan key:generate --show');
            $ok = false;
        }
        if (preg_match('/^APP_DEBUG=true/m', $env)) {
            $this->components->warn('APP_DEBUG=true en producción expone trazas y credenciales. Ponlo en false.');
        }

        return $ok;
    }

    /**
     * Copia la estructura de storage/ con sus .gitignore (para que el cliente
     * FTP cree las carpetas), sin logs, sesiones ni vistas compiladas locales.
     */
    private function copyStorageSkeleton(Filesystem $fs, string $from, string $to): void
    {
        $dirs = Finder::create()->directories()->in($from)->exclude('framework/testing');
        $fs->ensureDirectoryExists($to);
        foreach ($dirs as $dir) {
            $fs->ensureDirectoryExists($to.'/'.$dir->getRelativePathname());
        }
        foreach (Finder::create()->files()->in($from)->exclude('framework/testing')->ignoreDotFiles(false)->name('.gitignore') as $file) {
            $fs->copy($file->getPathname(), $to.'/'.$file->getRelativePathname());
        }
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
