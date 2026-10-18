<?php

use App\Actions\Members\CreateMember;
use App\Actions\Payments\CancelPayment;
use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendMembershipCard;
use App\Models\Member;
use App\Models\Membership;
use App\Services\Fees;
use App\Services\MembershipCard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
});

function newMemberOn(string $date, array $attributes = []): Member
{
    travelTo(CarbonImmutable::parse($date)->setTime(10, 0));

    return app(CreateMember::class)->handle(
        ['first_name' => 'Mees', 'last_name' => 'Jonkman', 'email' => 'mees@example.nl', ...$attributes],
        withMembership: true,
    );
}

describe('rates', function () {
    it('charges new members by the date they sign up', function (string $date, int $cents) {
        expect(app(Fees::class)->quoteForNewMember(CarbonImmutable::parse($date))->totalCents())->toBe($cents);
    })->with([
        ['2026-01-01', 9000],
        ['2026-03-31', 9000],
        ['2026-04-01', 6750],
        ['2026-06-30', 6750],
        ['2026-07-01', 4500],
        ['2026-09-30', 4500],
        ['2026-10-01', 2250],
        ['2026-11-30', 2250],
        ['2026-12-01', 10000],
        ['2026-12-31', 10000],
    ]);

    it('has an annual fee of 90 euros for renewals', function () {
        expect(app(Fees::class)->annualFeeCents())->toBe(9000);
    });
});

describe('new members', function () {
    it('get one membership until 31 December and one open payment', function () {
        $member = newMemberOn('2026-04-15');

        $membership = $member->memberships()->sole();
        $payment = $member->payments()->sole();

        expect($membership->starts_at->toDateString())->toBe('2026-04-15')
            ->and($membership->ends_at->toDateString())->toBe('2026-12-31')
            ->and($membership->status)->toBe(MembershipStatus::Pending)
            ->and($membership->payment->is($payment))->toBeTrue()
            ->and($payment->amount_cents)->toBe(6750)
            ->and($payment->description)->toBe('Contributie 2026 (instap vanaf 1 april)')
            ->and($payment->status)->toBe(PaymentStatus::Open);
    });

    it('signing up in December pay 10 + 90 euros in one payment for the rest of this year and all of next year', function () {
        $member = newMemberOn('2026-12-05');

        $memberships = $member->memberships()->orderBy('starts_at')->get();
        $payment = $member->payments()->sole();

        expect($memberships)->toHaveCount(2)
            ->and($memberships[0]->starts_at->toDateString())->toBe('2026-12-05')
            ->and($memberships[0]->ends_at->toDateString())->toBe('2026-12-31')
            ->and($memberships[1]->starts_at->toDateString())->toBe('2027-01-01')
            ->and($memberships[1]->ends_at->toDateString())->toBe('2027-12-31')
            ->and($memberships->pluck('payment_id')->unique()->all())->toBe([$payment->id])
            ->and($payment->amount_cents)->toBe(10000)
            ->and($payment->description)->toBe('Contributie 2026 (instap vanaf 1 december) en 2027');
    });
});

describe('marking as paid', function () {
    it('activates the membership, queues the card and logs it', function () {
        $member = newMemberOn('2026-10-18');
        $payment = $member->payments()->sole();

        $note = app(MarkPaymentPaid::class)->handle($payment, PaymentMethod::BankTransfer, CarbonImmutable::parse('2026-10-20'), 'NL12 overboeking');

        expect($note)->toBeNull()
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->fresh()->paid_at->toDateString())->toBe('2026-10-20')
            ->and($payment->fresh()->reference)->toBe('NL12 overboeking')
            ->and($member->memberships()->sole()->status)->toBe(MembershipStatus::Active)
            ->and(Activity::where('event', 'paid')->exists())->toBeTrue();

        Queue::assertPushed(SendMembershipCard::class, fn ($job) => $job->membership->is($member->memberships()->sole()));
    });

    it('activates both December periods and sends the card for next year', function () {
        $member = newMemberOn('2026-12-05');

        app(MarkPaymentPaid::class)->handle($member->payments()->sole(), PaymentMethod::Cash);

        expect($member->memberships()->where('status', MembershipStatus::Active)->count())->toBe(2);
        Queue::assertPushed(SendMembershipCard::class, 1);
        Queue::assertPushed(SendMembershipCard::class, fn ($job) => $job->membership->cardYear() === 2027);
    });

    it('lets a December member in this December with the 2027 card, valid until 31-12-2027', function () {
        $member = newMemberOn('2026-12-05', ['member_number' => 7]);
        app(MarkPaymentPaid::class)->handle($member->payments()->sole(), PaymentMethod::Cash);
        $card2027 = $member->memberships()->whereYear('starts_at', 2027)->sole();

        travelTo(CarbonImmutable::parse('2026-12-20'));
        parse_str(parse_url(app(MembershipCard::class)->qrPayload($card2027), PHP_URL_QUERY), $params);

        get(route('card.verify', $params))
            ->assertSee('Geldig lid')
            ->assertSee('Geldig t/m 31-12-2027');
    });

    it('does not mail a card to members without e-mail, but still activates them', function () {
        $member = newMemberOn('2026-10-18', ['email' => null]);

        $note = app(MarkPaymentPaid::class)->handle($member->payments()->sole(), PaymentMethod::Cash);

        expect($note)->toContain('geen e-mailadres')
            ->and($member->memberships()->sole()->status)->toBe(MembershipStatus::Active);
        Queue::assertNothingPushed();
    });

    it('can skip mailing the card', function () {
        $member = newMemberOn('2026-10-18');

        app(MarkPaymentPaid::class)->handle($member->payments()->sole(), PaymentMethod::Cash, sendCard: false);

        Queue::assertNotPushed(SendMembershipCard::class);
    });

    it('refuses to mark a payment paid twice', function () {
        $payment = newMemberOn('2026-10-18')->payments()->sole();
        app(MarkPaymentPaid::class)->handle($payment, PaymentMethod::Cash);

        app(MarkPaymentPaid::class)->handle($payment->fresh(), PaymentMethod::Cash);
    })->throws(InvalidArgumentException::class);

    it('does not reactivate a period that was cancelled in the meantime', function () {
        $member = newMemberOn('2026-10-18');
        $member->memberships()->update(['status' => MembershipStatus::Cancelled]);

        app(MarkPaymentPaid::class)->handle($member->payments()->sole(), PaymentMethod::Cash);

        expect($member->memberships()->sole()->status)->toBe(MembershipStatus::Cancelled);
    });
});

it('cancels an open payment together with the period awaiting it', function () {
    $member = newMemberOn('2026-10-18');

    app(CancelPayment::class)->handle($member->payments()->sole(), 'Wordt toch geen lid');

    expect($member->payments()->sole()->status)->toBe(PaymentStatus::Cancelled)
        ->and($member->memberships()->sole()->status)->toBe(MembershipStatus::Cancelled);
});

it('keeps active memberships when a payment is cancelled', function () {
    $membership = Membership::factory()->create();
    $payment = $membership->member->payments()->create(['description' => 'Test', 'amount_cents' => 100, 'status' => 'open']);
    $membership->update(['payment_id' => $payment->id]);

    app(CancelPayment::class)->handle($payment);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Active);
});
