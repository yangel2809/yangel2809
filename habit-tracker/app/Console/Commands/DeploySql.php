<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

/**
 * Genera el SQL de las migraciones para importarlo en phpMyAdmin
 * (hosting sin acceso a artisan). No ejecuta nada contra la BD.
 *
 *   php artisan deploy:sql --all        Instalación desde cero (todas las migraciones)
 *   php artisan deploy:sql              Solo las pendientes según la BD local
 */
class DeploySql extends Command
{
    protected $signature = 'deploy:sql
        {--all : Todas las migraciones, para una BD vacía}
        {--output= : Archivo de salida (por defecto, la consola)}';

    protected $description = 'Exporta el SQL de las migraciones para importarlo con phpMyAdmin';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->laravel->make('migrator');
        /** @var Connection $connection */
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql' && $connection->getDriverName() !== 'mariadb') {
            $this->error('La conexión por defecto debe ser MySQL/MariaDB para generar SQL compatible.');

            return self::FAILURE;
        }

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        ksort($files);

        if (! $this->option('all')) {
            if (! $migrator->repositoryExists()) {
                $this->error('La BD local no tiene tabla migrations. Usa --all o corre "php artisan migrate" primero.');

                return self::FAILURE;
            }
            $files = array_diff_key($files, array_flip($migrator->getRepository()->getRan()));
        }

        if ($files === []) {
            $this->info('No hay migraciones pendientes.');

            return self::SUCCESS;
        }

        $table = config('database.migrations.table', 'migrations');
        $table = is_array($table) ? ($table['table'] ?? 'migrations') : $table;

        $out = [
            '-- Generado con: php artisan deploy:sql'.($this->option('all') ? ' --all' : ''),
            '-- Importar en phpMyAdmin con la base de datos seleccionada.',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            '',
        ];

        if ($this->option('all')) {
            $out[] = '-- Tabla de control de migraciones';
            $out = [...$out, ...$this->pretend($connection, fn () => $migrator->getRepository()->createRepository())];
            $out[] = '';
        }

        $out[] = "SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `{$table}`);";
        $out[] = '';

        foreach ($files as $name => $path) {
            $migration = require $path;
            $out[] = "-- {$name}";
            $out = [...$out, ...$this->pretend($connection, fn () => $migration->up())];
            $out[] = sprintf('INSERT INTO `%s` (`migration`, `batch`) VALUES (%s, @batch);', $table, $this->quote($name));
            $out[] = '';
        }

        $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $sql = implode("\n", $out)."\n";

        if ($file = $this->option('output')) {
            file_put_contents($file, $sql);
            $this->info(count($files)." migraciones exportadas a {$file}");
        } else {
            $this->line($sql);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function pretend(Connection $connection, callable $callback): array
    {
        return array_map(function (array $q) use ($connection) {
            $sql = $q['bindings'] === []
                ? $q['query']
                : $connection->getQueryGrammar()->substituteBindingsIntoRawSql($q['query'], $connection->prepareBindings($q['bindings']));

            return rtrim($sql, ';').';';
        }, $connection->pretend($callback));
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
