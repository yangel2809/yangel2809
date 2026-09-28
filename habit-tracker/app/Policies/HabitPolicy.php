<?php

namespace App\Policies;

use App\Models\Habit;
use App\Models\User;

class HabitPolicy
{
    public function manage(User $user, Habit $habit): bool
    {
        return $habit->user_id === $user->id;
    }
}
