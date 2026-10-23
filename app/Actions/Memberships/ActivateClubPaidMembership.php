<?php

namespace App\Actions\Memberships;

use App\Actions\Accounts\LinkMemberAccount;
use App\Actions\Cards\QueueMembershipCard;
use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The club (e.g. Mosa Petanca) has paid for its members: a period awaiting the club's payment becomes active
 * and the card is e-mailed. Members who pay themselves are activated through "Markeer als betaald" instead.
 */
class ActivateClubPaidMembership
{
    public function __construct(private QueueMembershipCard $cards) {}

    /**
     * @return string|null Why no card was e-mailed, or null when it was queued.
     */
    public function handle(Membership $membership, bool $sendCard = true): ?string
    {
        if (! self::canActivate($membership)) {
            throw new InvalidArgumentException('Alleen een lidmaatschap dat wacht op betaling door de vereniging kan zo worden geactiveerd.');
        }

        $club = $membership->member->club;

        DB::transaction(function () use ($membership, $club) {
            $membership->update(['status' => MembershipStatus::Active]);

            activity('memberships')
                ->performedOn($membership)
                ->event('activated_by_club')
                ->withProperties(['club' => $club?->name])
                ->log('Lidmaatschap actief: betaald via '.($club?->name ?? 'de vereniging'));
        });

        app(LinkMemberAccount::class)->forMember($membership->member);

        return $sendCard ? $this->cards->queueIfPossible($membership) : 'Kaart versturen is uitgezet.';
    }

    /**
     * The member's period in $year that waits for the club's payment, if any.
     */
    public static function pendingFor(Member $member, int $year): ?Membership
    {
        return $member->memberships()
            ->where('status', MembershipStatus::Pending)
            ->whereNull('payment_id')
            ->whereDate('starts_at', '>=', "{$year}-01-01")
            ->whereDate('starts_at', '<=', "{$year}-12-31")
            ->first();
    }

    /**
     * Awaiting payment without an individual payment: the club pays.
     */
    public static function canActivate(Membership $membership): bool
    {
        return $membership->status === MembershipStatus::Pending && $membership->payment_id === null;
    }
}
