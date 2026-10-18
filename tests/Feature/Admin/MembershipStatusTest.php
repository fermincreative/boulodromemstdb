<?php

use App\Actions\Memberships\UpdateMembership;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Jobs\SendMemberMail;
use App\Jobs\SendMembershipCard;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 18));
    actingAs(User::factory()->admin()->create());
    $this->member = Member::factory()->create(['email' => 'lid@example.nl']);
});

function membershipsTab(Member $member)
{
    return Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => ViewMember::class]);
}

it('cannot switch a membership awaiting payment to active by editing it', function () {
    $membership = Membership::factory()->pending()->for($this->member)->create();

    membershipsTab($this->member)
        ->callAction(TestAction::make('edit')->table($membership), ['status' => 'active'])
        ->assertHasFormErrors(['status']);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Pending);
    Queue::assertNothingPushed();
});

it('refuses manual activation at the action level too', function (MembershipStatus $from) {
    $membership = Membership::factory()->for($this->member)->create(['status' => $from]);

    app(UpdateMembership::class)->handle($membership, $membership->starts_at, $membership->ends_at, MembershipStatus::Active);
})->with([MembershipStatus::Pending, MembershipStatus::Expired, MembershipStatus::Cancelled])
    ->throws(InvalidArgumentException::class);

it('still lets an active membership expire or be cancelled', function () {
    $membership = Membership::factory()->for($this->member)->create();

    app(UpdateMembership::class)->handle($membership, $membership->starts_at, $membership->ends_at, MembershipStatus::Cancelled);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Cancelled);
});

it('activates through "Markeer als betaald", with confirmation and card', function () {
    $membership = Membership::factory()->pending()->for($this->member)->create();
    $payment = $this->member->payments()->create(['description' => 'Contributie 2026', 'amount_cents' => 9000, 'status' => 'open']);
    $membership->update(['payment_id' => $payment->id]);

    membershipsTab($this->member)
        ->callAction(TestAction::make('markPaid')->table($membership), [
            'method' => 'bank_transfer', 'paid_at' => '18-10-2026', 'send_card' => true, 'send_confirmation' => true,
        ])
        ->assertHasNoFormErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Queue::assertPushed(SendMembershipCard::class);
    Queue::assertPushed(SendMemberMail::class);
});

it('records a payment for an older membership that has none, then activates it', function () {
    $membership = Membership::factory()->pending()->for($this->member)->create();

    membershipsTab($this->member)
        ->callAction(TestAction::make('markPaid')->table($membership), [
            'amount' => '45,00', 'method' => 'cash', 'paid_at' => '18-10-2026', 'send_card' => false, 'send_confirmation' => false,
        ])
        ->assertHasNoFormErrors();

    $membership->refresh();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->payment->amount_cents)->toBe(4500)
        ->and($membership->payment->status)->toBe(PaymentStatus::Paid);
});

it('adds a membership as awaiting payment with an open payment', function () {
    membershipsTab($this->member)
        ->callAction(TestAction::make('create')->table(), ['starts_at' => '2027-01-01', 'ends_at' => '2027-12-31', 'amount' => '90'])
        ->assertHasNoFormErrors();

    $membership = $this->member->memberships()->sole();
    expect($membership->status)->toBe(MembershipStatus::Pending)
        ->and($membership->payment->status)->toBe(PaymentStatus::Open)
        ->and($membership->payment->amount_cents)->toBe(9000)
        ->and($membership->payment->description)->toBe('Contributie 2027');
    Queue::assertNothingPushed();
});

it('suggests the year after the latest period when adding one', function () {
    Membership::factory()->forYear(2026)->for($this->member)->create();

    membershipsTab($this->member)
        ->mountAction(TestAction::make('create')->table())
        ->assertSchemaStateSet(['starts_at' => '2027-01-01', 'ends_at' => '2027-12-31', 'amount' => '90,00']);
});

it('withdraws a membership awaiting payment together with its payment', function () {
    membershipsTab($this->member)
        ->callAction(TestAction::make('create')->table(), ['starts_at' => '2027-01-01', 'ends_at' => '2027-12-31', 'amount' => '90']);
    $membership = $this->member->memberships()->sole();

    membershipsTab($this->member)
        ->callAction(TestAction::make('cancelPending')->table($membership));

    expect($membership->fresh()->status)->toBe(MembershipStatus::Cancelled)
        ->and($membership->payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('lets treasurers register payments here but not edit periods', function () {
    actingAs(User::factory()->treasurer()->create());
    $membership = Membership::factory()->pending()->for($this->member)->create();

    membershipsTab($this->member)
        ->assertActionVisible(TestAction::make('markPaid')->table($membership))
        ->assertActionHidden(TestAction::make('edit')->table($membership));
});

it('never deletes memberships', function () {
    $membership = Membership::factory()->pending()->for($this->member)->create();

    expect(auth()->user()->can('delete', $membership))->toBeFalse();
});
