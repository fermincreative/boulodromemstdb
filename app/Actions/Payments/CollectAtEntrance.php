<?php

namespace App\Actions\Payments;

use App\Actions\Memberships\RenewalOutcome;
use App\Actions\Memberships\RenewMembership;
use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Staff scanned a card at the entrance and the member pays cash on the spot: the usual payment process
 * (membership active, confirmation and card e-mailed), marked as received at the entrance.
 */
class CollectAtEntrance
{
    public const REFERENCE = 'Contant bij de ingang';

    public function __construct(private MarkPaymentPaid $markPaid, private RenewMembership $renew) {}

    /**
     * An open payment (e.g. the renewal) is paid in cash.
     */
    public function payment(Payment $payment): void
    {
        $this->markPaid->handle($payment, PaymentMethod::Cash, today(), self::REFERENCE);
    }

    /**
     * Not renewed yet for $year: renew now (without the renewal e-mail) and take the cash at once.
     */
    public function renewAndCollect(Member $member, int $year): Payment
    {
        if (! $this->canRenew($member, $year)) {
            throw new InvalidArgumentException("Dit lid kan niet voor {$year} worden verlengd.");
        }

        return DB::transaction(function () use ($member, $year) {
            if ($this->renew->handle($member, $year, sendMail: false) === RenewalOutcome::Skipped) {
                throw new InvalidArgumentException("Dit lid kan niet voor {$year} worden verlengd.");
            }

            $payment = RenewMembership::openPaymentFor($member, $year);
            $this->payment($payment);

            return $payment->refresh();
        });
    }

    /**
     * Can be renewed for $year and pays individually (members of a club that pays are activated by the club).
     */
    public function canRenew(Member $member, int $year): bool
    {
        return ! $member->paysCollectively() && Member::whereKey($member->getKey())->renewable($year)->exists();
    }
}
