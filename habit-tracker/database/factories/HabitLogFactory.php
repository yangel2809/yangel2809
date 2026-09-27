<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitLog>
 */
class HabitLogFactory extends Factory
{
    protected $model = HabitLog::class;

    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'date' => LocalDate::today()->format('Y-m-d'),
            'completed' => true,
            'note' => null,
        ];
    }
}
