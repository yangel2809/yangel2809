<?php

namespace Database\Seeders;

use App\Models\DailyPriority;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para desarrollo: php artisan db:seed --class=DemoSeeder
 * Usa el primer usuario (o crea demo@example.com / password). NO usar en producción.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        $user = User::query()->first() ?? User::factory()->create(['name' => 'Demo', 'email' => 'demo@example.com']);
        $today = LocalDate::today();
        $start = $today->subDays(59);

        $defs = [
            ['Leer 20 min', 'daily', null, '#3b82f6', [1 => .45, 2 => .8, 3 => .85, 4 => .8, 5 => .7, 6 => .6, 7 => .75]],
            ['Sin redes antes de las 9', 'daily', null, '#8b5cf6', [1 => .3, 2 => .65, 3 => .7, 4 => .6, 5 => .5, 6 => .4, 7 => .5]],
            ['Tomar 2 L de agua', 'daily', null, '#10b981', array_fill(1, 7, .92)],
            ['Gimnasio', 'weekly', 3, '#f97316', [1 => .6, 2 => .2, 3 => .6, 4 => .2, 5 => .5, 6 => .3, 7 => .1]],
        ];
        $notes = ['Cansado, pero cumplí', 'Buen ritmo', 'Solo la mitad', 'Día pesado en el trabajo', 'Me costó arrancar'];

        foreach ($defs as $i => [$name, $freq, $target, $color, $p]) {
            $habit = Habit::query()->updateOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                ['frequency_type' => $freq, 'weekly_target' => $target, 'color' => $color, 'start_date' => $start->format('Y-m-d'), 'sort_order' => $i + 1],
            );
            $habit->logs()->delete();

            for ($d = $start; $d->lt($today); $d = $d->addDay()) {
                if (mt_rand() / mt_getrandmax() < $p[$d->isoWeekday()]) {
                    HabitLog::query()->create([
                        'habit_id' => $habit->id,
                        'date' => $d->format('Y-m-d'),
                        'completed' => true,
                        'note' => mt_rand(1, 8) === 1 ? $notes[array_rand($notes)] : null,
                    ]);
                }
            }
        }

        DailyPriority::query()->where('user_id', $user->id)->delete();
        $tasks = ['Enviar cotización', 'Revisar PR de pagos', 'Llamar al proveedor', 'Cerrar reporte REKO', 'Actualizar servidor', 'Preparar demo'];
        for ($d = $today->subDays(20); $d->lte($today->addDay()); $d = $d->addDay()) {
            foreach (range(1, mt_rand(1, 3)) as $pos) {
                DailyPriority::query()->create([
                    'user_id' => $user->id,
                    'date' => $d->format('Y-m-d'),
                    'position' => $pos,
                    'text' => $tasks[array_rand($tasks)],
                    'completed' => $d->lt($today) ? mt_rand(1, 10) <= 7 : null,
                ]);
            }
        }
    }
}
