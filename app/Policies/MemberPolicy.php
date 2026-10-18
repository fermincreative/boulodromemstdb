<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

/**
 * Everyone with panel access may look at members; only admins change them.
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Member $member): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Member $member): bool
    {
        return $user->isAdmin() && ! $member->isCancelled();
    }

    public function reinstate(User $user, Member $member): bool
    {
        return $user->isAdmin() && $member->isCancelled();
    }

    /**
     * Bulk export of personal data: admins only (and logged).
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Members are never deleted from the panel: cancel them instead, so history is kept.
     */
    public function delete(User $user, Member $member): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
