<?php

namespace App\Actions\Cards;

use App\Enums\MembershipStatus;
use App\Jobs\SendMembershipCard;
use App\Models\Membership;
use InvalidArgumentException;

/**
 * Queues the card e-mail for a membership. Cancelled members and members without e-mail get no mail.
 */
class QueueMembershipCard
{
    /**
     * Queues the card e-mail, optionally with a subject and body edited for this one send.
     */
    public function handle(Membership $membership, ?string $subject = null, ?string $body = null): void
    {
        if ($reason = $this->cannotSendReason($membership)) {
            throw new InvalidArgumentException($reason);
        }

        SendMembershipCard::dispatch($membership, $subject, $body, auth()->id());
    }

    public function cannotSendReason(Membership $membership): ?string
    {
        $member = $membership->member;

        return match (true) {
            $member->hasLeft() || ($member->isCancelled() && $membership->starts_at->gt($member->cancelled_at)) => 'Dit lid is opgezegd en krijgt geen kaart meer voor deze periode.',
            blank($member->email) => 'Dit lid heeft geen e-mailadres. Download de printversie en geef die af.',
            $membership->status !== MembershipStatus::Active => 'Alleen een actief lidmaatschap krijgt een kaart.',
            default => null,
        };
    }
}
