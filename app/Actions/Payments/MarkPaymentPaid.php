<?php

namespace App\Actions\Payments;

use App\Actions\Accounts\LinkMemberAccount;
use App\Actions\Cards\QueueMembershipCard;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendMemberMail;
use App\Models\MailTemplate;
use App\Models\Payment;
use App\Services\MailTemplates;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Payment received → its memberships become active → payment confirmation and the card for the latest period are e-mailed.
 */
class MarkPaymentPaid
{
    public function __construct(
        private QueueMembershipCard $cards,
        private MailTemplates $templates,
        private LinkMemberAccount $accounts,
    ) {}

    /**
     * @param  bool  $sendConfirmation  E-mail the member a payment confirmation (the "Betalingsbevestiging" text).
     * @return string|null Why no card was e-mailed (e.g. no e-mail address), or null when it was queued.
     */
    public function handle(Payment $payment, PaymentMethod $method, ?CarbonInterface $paidAt = null, ?string $reference = null, bool $sendCard = true, bool $sendConfirmation = true): ?string
    {
        if (! $payment->isOpen()) {
            throw new InvalidArgumentException('Alleen een openstaande betaling kan als betaald worden gemarkeerd.');
        }

        $latest = DB::transaction(function () use ($payment, $method, $paidAt, $reference) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'method' => $method,
                'paid_at' => ($paidAt ?? today())->toDateString(),
                'reference' => $reference ?: $payment->reference,
            ]);

            $memberships = $payment->memberships()->where('status', MembershipStatus::Pending)->get();
            $memberships->each->update(['status' => MembershipStatus::Active]);

            activity('payments')
                ->performedOn($payment)
                ->event('paid')
                ->withProperties(['amount_cents' => $payment->amount_cents, 'method' => $method->value])
                ->log('Betaling ontvangen');

            return $memberships->sortByDesc('ends_at')->first();
        });

        $member = $payment->member;
        // Now established: a sign-up on an address that already has a login joins it (see LinkMemberAccount).
        $this->accounts->forMember($member);

        if ($sendConfirmation && filled($member->email)) {
            $mail = $this->templates->renderPaymentConfirmation($payment->fresh());
            SendMemberMail::dispatch($member, MailTemplate::PAYMENT_CONFIRMATION, $mail['subject'], $mail['body'], auth()->id());
        }

        if (! $sendCard) {
            return 'Kaart versturen is uitgezet.';
        }

        if (! $latest) {
            return 'Er hoort geen lidmaatschap bij deze betaling.';
        }

        return $this->cards->queueIfPossible($latest);
    }
}
