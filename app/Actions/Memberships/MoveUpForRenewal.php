<?php

namespace App\Actions\Memberships;

use App\Models\Member;

/**
 * Moves a member to "Te verlengen" for a year before their membership is within the renewal window
 * (e.g. someone who asked to renew early), or back to "Nog niet aan de beurt".
 */
class MoveUpForRenewal
{
    /**
     * @return bool Whether anything changed.
     */
    public function handle(Member $member, int $year): bool
    {
        if ($member->renew_early_for === $year || ! Member::whereKey($member->getKey())->renewable($year)->exists()) {
            return false;
        }

        $member->forceFill(['renew_early_for' => $year])->saveQuietly();

        activity('members')
            ->performedOn($member)
            ->event('renewal_moved_up')
            ->withProperties(['year' => $year])
            ->log("Naar Te verlengen gezet voor {$year} (vervroegd)");

        return true;
    }

    /**
     * @return bool Whether anything changed (only members moved up by hand can be moved back).
     */
    public function undo(Member $member, int $year): bool
    {
        if ($member->renew_early_for !== $year) {
            return false;
        }

        $member->forceFill(['renew_early_for' => null])->saveQuietly();

        activity('members')
            ->performedOn($member)
            ->event('renewal_moved_back')
            ->withProperties(['year' => $year])
            ->log("Terug naar Nog niet aan de beurt voor {$year}");

        return true;
    }
}
