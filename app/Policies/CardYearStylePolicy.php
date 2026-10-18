<?php

namespace App\Policies;

use App\Models\CardYearStyle;
use App\Models\User;

class CardYearStylePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CardYearStyle $style): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CardYearStyle $style): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CardYearStyle $style): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
