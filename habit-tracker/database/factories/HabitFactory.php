<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    protected $model = Habit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst($this->faker->words(2, true)),
            'frequency_type' => Habit::DAILY,
            'weekly_target' => null,
            'color' => '#10b981',
            'start_date' => LocalDate::today()->subDays(60)->format('Y-m-d'),
        ];
    }

    public function weekly(int $target = 3): static
    {
        return $this->state(['frequency_type' => Habit::WEEKLY, 'weekly_target' => $target]);
    }

    public function startingOn(string $date): static
    {
        return $this->state(['start_date' => $date]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
