<?php

namespace Database\Factories;

use App\Models\DailyPriority;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyPriority>
 */
class DailyPriorityFactory extends Factory
{
    protected $model = DailyPriority::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => LocalDate::today()->format('Y-m-d'),
            'position' => 1,
            'text' => $this->faker->sentence(4),
            'completed' => null,
        ];
    }
}
