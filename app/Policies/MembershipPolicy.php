<?php

namespace App\Policies;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Membership $membership): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->isAdmin();
    }

    /**
     * History is never thrown away; a period awaiting payment is withdrawn with "Annuleren" instead.
     */
    public function delete(User $user, Membership $membership): bool
    {
        return false;
    }

    public function markPaid(User $user, Membership $membership): bool
    {
        return $user->canManagePayments() && $membership->status === MembershipStatus::Pending;
    }

    public function cancelPending(User $user, Membership $membership): bool
    {
        return $user->canManagePayments() && $membership->status === MembershipStatus::Pending;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function downloadCard(User $user, Membership $membership): bool
    {
        return true;
    }

    public function sendCard(User $user, Membership $membership): bool
    {
        return $user->canManagePayments();
    }
}
