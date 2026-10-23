<?php

use App\Actions\Members\CreateMember;
use App\Actions\Memberships\ActivateClubPaidMembership;
use App\Actions\Memberships\RenewalOutcome;
use App\Actions\Memberships\RenewMembership;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendMemberMail;
use App\Jobs\SendMembershipCard;
use App\Models\Club;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Setting;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
});

function memberIn2026(?string $club = 'SBM Halpas', array $attributes = []): Member
{
    $member = Member::factory()->create([
        'email' => 'lid@example.nl',
        'club_id' => $club ? Club::firstWhere('name', $club)->id : null,
        ...$attributes,
    ]);
    Membership::factory()->forYear(2026)->for($member)->create();

    return $member;
}

function renew(Member $member, int $year = 2027, bool $sendMail = true): RenewalOutcome
{
    return app(RenewMembership::class)->handle($member, $year, $sendMail);
}

it('renews for next year from October on', function () {
    expect(RenewMembership::defaultYear())->toBe(2027);

    travelTo(now()->setDate(2027, 2, 1));
    expect(RenewMembership::defaultYear())->toBe(2027);
});

it('creates next year\'s period awaiting payment with an open payment for the annual fee', function () {
    $member = memberIn2026();

    expect(renew($member))->toBe(RenewalOutcome::Mailed);

    $membership = $member->memberships()->whereDate('starts_at', '2027-01-01')->sole();
    expect($membership->ends_at->toDateString())->toBe('2027-12-31')
        ->and($membership->status)->toBe(MembershipStatus::Pending)
        ->and($membership->payment->status)->toBe(PaymentStatus::Open)
        ->and($membership->payment->amount_cents)->toBe(9000)
        ->and($membership->payment->description)->toBe('Contributie 2027')
        ->and(Activity::where('event', 'renewed')->sole()->description)->toBe('Verlengd voor 2027');
});

it('e-mails the renewal with the iDEAL link and the bank details', function () {
    $member = memberIn2026();
    renew($member);

    $payment = $member->payments()->sole();
    Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::RENEWAL
        && $job->subject === 'Verlenging lidmaatschap 2027 — Boulodrôme Maastricht'
        && str_contains($job->body, '**€ 90,00**')
        && str_contains($job->body, 'U betaalt eenvoudig met iDEAL of Bancontact via deze link')
        && str_contains($job->body, "/betalen/{$payment->id}?signature=")
        && str_contains($job->body, 'NL96 ABNA 0617 0464 84')
        && str_contains($job->body, 'Contributie 2027 '.$member->display_number));
});

it('is idempotent: renewing twice creates nothing extra', function () {
    $member = memberIn2026();

    renew($member);
    expect(renew($member))->toBe(RenewalOutcome::Skipped)
        ->and($member->memberships()->count())->toBe(2)
        ->and($member->payments()->count())->toBe(1);
    Queue::assertPushed(SendMemberMail::class, 1);
});

it('does not renew members who gave notice or were not active last year', function (Member $member) {
    expect(renew($member))->toBe(RenewalOutcome::Skipped)
        ->and($member->memberships()->whereDate('starts_at', '2027-01-01')->exists())->toBeFalse();
})->with([
    'gave notice' => fn () => memberIn2026(attributes: ['cancelled_at' => '2026-12-31']),
    'only 2025' => fn () => tap(Member::factory()->create(), fn (Member $m) => Membership::factory()->forYear(2025)->for($m)->create()),
    'never paid 2026' => fn () => tap(Member::factory()->create(), fn (Member $m) => Membership::factory()->forYear(2026)->pending()->for($m)->create()),
]);

it('can renew again after a withdrawn renewal for the same year', function () {
    $member = memberIn2026();
    renew($member);
    $member->memberships()->whereDate('starts_at', '2027-01-01')->update(['status' => MembershipStatus::Cancelled]);

    expect(renew($member))->toBe(RenewalOutcome::Mailed)
        ->and($member->memberships()->whereDate('starts_at', '2027-01-01')->sole()->status)->toBe(MembershipStatus::Pending)
        ->and($member->payments()->count())->toBe(2);
});

it('prepares the payment without e-mail for members without an e-mail address', function () {
    $member = memberIn2026(attributes: ['email' => null]);

    expect(renew($member))->toBe(RenewalOutcome::NotMailed)
        ->and($member->payments()->sole()->isOpen())->toBeTrue();
    Queue::assertNothingPushed();
});

it('refuses to ask for nothing when no annual fee is set', function () {
    Setting::put(Setting::ANNUAL_FEE_CENTS, 0);

    renew(memberIn2026());
})->throws(InvalidArgumentException::class, 'Stel eerst de jaarcontributie in');

describe('members whose club pays', function () {
    it('renews them awaiting the club, without payment or e-mail', function (string $club) {
        $member = memberIn2026($club);

        expect(renew($member))->toBe(RenewalOutcome::ViaClub);

        $membership = $member->memberships()->whereDate('starts_at', '2027-01-01')->sole();
        expect($membership->status)->toBe(MembershipStatus::Pending)
            ->and($membership->payment_id)->toBeNull()
            ->and($member->payments()->count())->toBe(0)
            ->and(Activity::where('event', 'renewed')->sole()->description)->toBe("Verlengd voor 2027 (betaling via {$club})");
        Queue::assertNothingPushed();
    })->with(['Mosa Petanca', 'JBC Amby']);

    it('activates them once the club has paid, and mails the card', function () {
        $member = memberIn2026('Mosa Petanca');
        renew($member);
        $membership = ActivateClubPaidMembership::pendingFor($member, 2027);

        expect(app(ActivateClubPaidMembership::class)->handle($membership))->toBeNull()
            ->and($membership->fresh()->status)->toBe(MembershipStatus::Active)
            ->and(Activity::where('event', 'activated_by_club')->sole()->description)->toBe('Lidmaatschap actief: betaald via Mosa Petanca');
        Queue::assertPushed(SendMembershipCard::class, fn (SendMembershipCard $job) => $job->membership->is($membership));
    });

    it('cannot activate a period that has its own payment', function () {
        $member = memberIn2026();
        renew($member);

        app(ActivateClubPaidMembership::class)->handle($member->memberships()->whereDate('starts_at', '2027-01-01')->sole());
    })->throws(InvalidArgumentException::class);

    it('gives new members of such a club no joining payment', function () {
        $member = app(CreateMember::class)->handle(
            ['first_name' => 'Nieuw', 'last_name' => 'Lid', 'club_id' => Club::firstWhere('name', 'JBC Amby')->id],
            withMembership: true,
        );

        expect($member->payments()->count())->toBe(0)
            ->and($member->memberships()->sole()->status)->toBe(MembershipStatus::Pending)
            ->and(ActivateClubPaidMembership::canActivate($member->memberships()->sole()))->toBeTrue();
    });
});
