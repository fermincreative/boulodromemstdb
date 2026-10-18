<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Club $club): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->isAdmin() && ! $club->members()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
