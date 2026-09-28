<?php

namespace App\Policies;

use App\Models\DailyPriority;
use App\Models\User;

class DailyPriorityPolicy
{
    public function manage(User $user, DailyPriority $priority): bool
    {
        return $priority->user_id === $user->id;
    }
}
