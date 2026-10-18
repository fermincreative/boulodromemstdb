<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Edits a membership period. The status can never be set to "active" by hand: that only happens
 * through a registered payment (MarkPaymentPaid), so the confirmation and card are sent.
 */
class UpdateMembership
{
    public function handle(Membership $membership, CarbonInterface $startsAt, CarbonInterface $endsAt, MembershipStatus $status): Membership
    {
        if (! in_array($status, self::allowedStatuses($membership), true)) {
            throw new InvalidArgumentException(match (true) {
                $membership->status === MembershipStatus::Pending => 'Een lidmaatschap dat wacht op betaling wordt actief via "Markeer als betaald" of vervalt via "Annuleren".',
                $status === MembershipStatus::Active => 'Een lidmaatschap kan niet met de hand op actief worden gezet; registreer een betaling.',
                default => 'Deze statuswijziging is niet toegestaan.',
            });
        }

        $membership->update([
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'status' => $status,
        ]);

        return $membership;
    }

    /**
     * Statuses a person may choose in the edit form.
     *
     * @return array<int, MembershipStatus>
     */
    public static function allowedStatuses(Membership $membership): array
    {
        return match ($membership->status) {
            MembershipStatus::Pending => [MembershipStatus::Pending],
            MembershipStatus::Active => [MembershipStatus::Active, MembershipStatus::Expired, MembershipStatus::Cancelled],
            MembershipStatus::Expired, MembershipStatus::Cancelled => [MembershipStatus::Expired, MembershipStatus::Cancelled],
        };
    }
}
