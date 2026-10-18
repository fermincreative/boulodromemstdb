<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only admins manage admin-panel users.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function resetMfa(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }
}
