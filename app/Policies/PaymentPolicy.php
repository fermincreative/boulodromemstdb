<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Everyone may look at payments; admins and treasurers register and cancel them. Payments are never edited or deleted.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    public function markPaid(User $user, Payment $payment): bool
    {
        return $user->canManagePayments() && $payment->isOpen();
    }

    public function markAnyPaid(User $user): bool
    {
        return $user->canManagePayments();
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $user->canManagePayments() && $payment->isOpen();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
