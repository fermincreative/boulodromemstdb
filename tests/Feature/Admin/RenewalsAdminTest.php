<?php

use App\Actions\Memberships\RenewMembership;
use App\Enums\MembershipStatus;
use App\Filament\Pages\Rates;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Renewals\Pages\ListRenewals;
use App\Filament\Resources\Renewals\RenewalResource;
use App\Jobs\SendMemberMail;
use App\Jobs\SendMembershipCard;
use App\Models\Club;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    // Within 42 days of 31-12, so 2026 memberships are up for renewal.
    travelTo(now()->setDate(2026, 12, 1)->setTime(12, 0));
});

function activeIn2026(string $club = 'SBM Halpas', array $attributes = []): Member
{
    $member = Member::factory()->create(['email' => fake()->unique()->safeEmail(), 'club_id' => Club::firstWhere('name', $club)->id, ...$attributes]);
    Membership::factory()->forYear(2026)->for($member)->create();

    return $member;
}

it('lists the members still to renew for next year', function () {
    actingAs(User::factory()->treasurer()->create());
    $due = activeIn2026();
    $viaClub = activeIn2026('Mosa Petanca');
    $gaveNotice = activeIn2026(attributes: ['cancelled_at' => '2026-12-31']);

    Livewire::test(ListRenewals::class)
        ->assertSee('Verlengingen 2027')
        ->assertCanSeeTableRecords([$due, $viaClub])
        ->assertCanNotSeeTableRecords([$gaveNotice])
        ->assertSee('Betaalverzoek met betaallink per e-mail')
        ->assertSee('Geen betaalverzoek: betaald via Mosa Petanca');

    expect(RenewalResource::getNavigationBadge())->toBe('2');
});

it('renews the ticked members and mails those who pay themselves', function () {
    actingAs(User::factory()->treasurer()->create());
    $ticked = activeIn2026();
    $viaClub = activeIn2026('JBC Amby');
    $unticked = activeIn2026();

    Livewire::test(ListRenewals::class)
        ->assertTableBulkActionVisible('renew')
        ->callTableBulkAction('renew', [$ticked, $viaClub], ['send_mail' => true])
        ->assertNotified('2 leden verlengd');

    expect($ticked->payments()->sole()->amount_cents)->toBe(9000)
        ->and($viaClub->payments()->count())->toBe(0)
        ->and($viaClub->memberships()->where('status', MembershipStatus::Pending)->exists())->toBeTrue()
        ->and($unticked->memberships()->count())->toBe(1);
    Queue::assertPushed(SendMemberMail::class, 1);
});

it('starts from the saved text and sends the text as edited, filled in per member', function () {
    actingAs(User::factory()->treasurer()->create());
    $members = collect([activeIn2026(attributes: ['first_name' => 'Anna']), activeIn2026(attributes: ['first_name' => 'Bram'])]);

    Livewire::test(ListRenewals::class)
        ->mountTableBulkAction('renew', $members)
        ->assertTableBulkActionDataSet(['subject' => 'Verlenging lidmaatschap {jaar} — {vereniging}'])
        ->setTableBulkActionData(['subject' => 'Op naar {jaar}!', 'body' => 'Hoi {voornaam}, betaal hier: {betaallink}'])
        ->callMountedTableBulkAction()
        ->assertHasNoTableBulkActionErrors();

    foreach ($members as $member) {
        $payment = $member->payments()->sole();
        Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->member->is($member)
            && $job->subject === 'Op naar 2027!'
            && str_starts_with($job->body, "Hoi {$member->first_name}, betaal hier: ")
            && str_contains($job->body, "/betalen/{$payment->id}?signature="));
    }

    expect(MailTemplate::forKey(MailTemplate::RENEWAL)->subject)->toBe('Verlenging lidmaatschap {jaar} — {vereniging}');
});

it('does not need a text when renewing without e-mail', function () {
    actingAs(User::factory()->treasurer()->create());
    $member = activeIn2026();

    Livewire::test(ListRenewals::class)
        ->callTableBulkAction('renew', [$member], ['send_mail' => false, 'subject' => '', 'body' => ''])
        ->assertHasNoTableBulkActionErrors();

    expect($member->payments()->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('shows renewed members per stage, with the right bulk action', function () {
    actingAs(User::factory()->treasurer()->create());
    $self = activeIn2026();
    $viaClub = activeIn2026('Mosa Petanca');
    app(RenewMembership::class)->handle($self, 2027);
    app(RenewMembership::class)->handle($viaClub, 2027);

    Livewire::test(ListRenewals::class)
        ->set('activeTab', 'awaiting_payment')
        ->assertCanSeeTableRecords([$self])
        ->assertCanNotSeeTableRecords([$viaClub])
        ->assertSee('Wacht op betaling · € 90,00')
        ->assertTableBulkActionVisible('remind')
        ->assertTableBulkActionHidden('renew')
        ->set('activeTab', 'awaiting_club')
        ->assertCanSeeTableRecords([$viaClub])
        ->assertCanNotSeeTableRecords([$self])
        ->assertSee('Wacht op betaling door Mosa Petanca')
        ->assertTableBulkActionVisible('activateByClub');
});

it('mails the payment link again as a reminder', function () {
    actingAs(User::factory()->treasurer()->create());
    $member = activeIn2026();
    app(RenewMembership::class)->handle($member, 2027);

    Livewire::test(ListRenewals::class)
        ->set('activeTab', 'awaiting_payment')
        ->callTableBulkAction('remind', [$member], ['subject' => 'Herinnering {jaar}', 'body' => 'Beste {voornaam}, nog even betalen: {betaallink}'])
        ->assertNotified('1 e-mail verstuurd');

    Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::RENEWAL
        && $job->subject === 'Herinnering 2027'
        && str_starts_with($job->body, "Beste {$member->first_name}, nog even betalen: "));
    Queue::assertPushed(SendMemberMail::class, 2);
});

it('activates members once their club has paid', function () {
    actingAs(User::factory()->treasurer()->create());
    $members = collect([activeIn2026('Mosa Petanca'), activeIn2026('Mosa Petanca')]);
    $members->each(fn (Member $member) => app(RenewMembership::class)->handle($member, 2027));

    Livewire::test(ListRenewals::class)
        ->set('activeTab', 'awaiting_club')
        ->callTableBulkAction('activateByClub', $members, ['send_card' => true])
        ->assertNotified('2 lidmaatschappen geactiveerd');

    expect(Membership::whereDate('starts_at', '2027-01-01')->where('status', MembershipStatus::Active)->count())->toBe(2);
    Queue::assertPushed(SendMembershipCard::class, 2);
});

it('can switch to this year for late renewals', function () {
    actingAs(User::factory()->treasurer()->create());
    $member = Member::factory()->create();
    Membership::factory()->forYear(2025)->for($member)->create();

    Livewire::test(ListRenewals::class)
        ->assertCanNotSeeTableRecords([$member])
        ->set('year', 2026)
        ->assertSee('Verlengingen 2026')
        ->assertCanSeeTableRecords([$member]);
});

it('lets viewers look but not renew', function () {
    actingAs(User::factory()->viewer()->create());
    activeIn2026();

    get(RenewalResource::getUrl())->assertOk();
    Livewire::test(ListRenewals::class)->assertTableBulkActionHidden('renew');
});

it('offers "Activeren" instead of "Markeer als betaald" on a club-paid period', function () {
    actingAs(User::factory()->treasurer()->create());
    $member = activeIn2026('JBC Amby');
    app(RenewMembership::class)->handle($member, 2027);
    $pending = $member->memberships()->whereDate('starts_at', '2027-01-01')->sole();

    Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])
        ->assertSee('Via JBC Amby')
        ->assertTableActionHidden('markPaid', $pending)
        ->callTableAction('activateByClub', $pending, ['send_card' => false]);

    expect($pending->fresh()->status)->toBe(MembershipStatus::Active);
});

describe('renewal window', function () {
    beforeEach(fn () => travelTo(now()->setDate(2026, 11, 15)));

    it('keeps members whose membership ends after the window under "Nog niet aan de beurt"', function () {
        actingAs(User::factory()->treasurer()->create());
        $member = activeIn2026();

        Livewire::test(ListRenewals::class)
            ->assertCanNotSeeTableRecords([$member])
            ->set('activeTab', 'not_yet_due')
            ->assertCanSeeTableRecords([$member])
            ->assertTableBulkActionVisible('moveUp')
            ->assertTableBulkActionHidden('renew');

        expect(RenewalResource::getNavigationBadge())->toBeNull();
    });

    it('follows the number of days set under Tarieven', function () {
        actingAs(User::factory()->admin()->create());
        $member = activeIn2026();

        Livewire::test(Rates::class)->set('data.renewal_window_days', 60)->call('save')->assertHasNoErrors();

        Livewire::test(ListRenewals::class)->assertCanSeeTableRecords([$member]);
        expect(RenewalResource::getNavigationBadge())->toBe('1');
    });

    it('rejects an impossible number of days', function () {
        actingAs(User::factory()->admin()->create());

        Livewire::test(Rates::class)->set('data.renewal_window_days', 0)->call('save')->assertHasErrors(['data.renewal_window_days']);

        expect(RenewMembership::windowDays())->toBe(42);
    });

    it('moves members up by hand, and back', function () {
        actingAs(User::factory()->treasurer()->create());
        $early = activeIn2026();
        $other = activeIn2026();

        Livewire::test(ListRenewals::class)
            ->set('activeTab', 'not_yet_due')
            ->callTableBulkAction('moveUp', [$early])
            ->assertNotified('1 lid naar Te verlengen gezet')
            ->assertCanNotSeeTableRecords([$early])
            ->assertCanSeeTableRecords([$other])
            ->set('activeTab', 'due')
            ->assertCanSeeTableRecords([$early])
            ->assertSee('Met de hand naar Te verlengen')
            ->callTableBulkAction('renew', [$early], ['send_mail' => false]);

        expect($early->payments()->count())->toBe(1)
            ->and(Activity::where('event', 'renewal_moved_up')->exists())->toBeTrue();

        $other->forceFill(['renew_early_for' => 2027])->save();

        Livewire::test(ListRenewals::class)
            ->callTableBulkAction('moveBack', [$other])
            ->assertNotified('1 lid teruggezet')
            ->assertCanNotSeeTableRecords([$other]);

        expect($other->fresh()->renew_early_for)->toBeNull();
    });

    it('treats late renewals for this year as due', function () {
        actingAs(User::factory()->treasurer()->create());
        $member = Member::factory()->create();
        Membership::factory()->forYear(2025)->for($member)->create();

        Livewire::test(ListRenewals::class)->set('year', 2026)->assertCanSeeTableRecords([$member]);
    });
});
