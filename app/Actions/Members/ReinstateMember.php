<?php

namespace App\Actions\Members;

use App\Models\Member;

/**
 * Undoes a cancellation ("opzegging intrekken").
 */
class ReinstateMember
{
    public function handle(Member $member): Member
    {
        if (! $member->isCancelled()) {
            return $member;
        }

        $previous = $member->cancelled_at->toDateString();

        $member->forceFill(['cancelled_at' => null])->saveQuietly();

        activity('members')
            ->performedOn($member)
            ->event('reinstated')
            ->withProperties(['previous_cancelled_at' => $previous])
            ->log('Opzegging ingetrokken');

        return $member;
    }
}
