<?php

namespace App\Policies;

use App\Models\ScheduleSlot;
use App\Models\User;

/**
 * Everyone with panel access may look at the Rooster; only admins change it.
 */
class ScheduleSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduleSlot $slot): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
