<?php

use App\Actions\Members\CreateMember as CreateMemberAction;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Jobs\SendMembershipCard;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
    travelTo(now()->setDate(2026, 10, 18)->setTime(14, 30));
});

it('creates a first membership from today until 31 December, awaiting payment, by default', function () {
    Livewire::test(CreateMember::class)
        ->assertSee('Loopt van vandaag (18-10-2026) tot en met 31-12-2026. Contributie: € 22,50 (instap vanaf 1 oktober).')
        ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman'])
        ->call('create')
        ->assertHasNoFormErrors();

    $membership = Member::firstWhere('last_name', 'Jonkman')->memberships()->sole();

    expect($membership->starts_at->toDateString())->toBe('2026-10-18')
        ->and($membership->ends_at->toDateString())->toBe('2026-12-31')
        ->and($membership->status)->toBe(MembershipStatus::Pending)
        ->and($membership->payment->amount_cents)->toBe(2250)
        ->and($membership->payment->status)->toBe(PaymentStatus::Open);
});

it('activates the membership and mails the card when paid on the spot', function () {
    Queue::fake();
    config(['membership_card.qr_secret' => 'test-secret']);

    Livewire::test(CreateMember::class)
        ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman', 'email' => 'mees@example.nl', 'payment' => 'cash'])
        ->call('create')
        ->assertHasNoFormErrors();

    $membership = Member::firstWhere('last_name', 'Jonkman')->memberships()->sole();

    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->payment->status)->toBe(PaymentStatus::Paid)
        ->and($membership->payment->method)->toBe(PaymentMethod::Cash);
    Queue::assertPushed(SendMembershipCard::class);
});

it('can skip the membership', function () {
    Livewire::test(CreateMember::class)
        ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman', 'add_membership' => false])
        ->assertFormFieldHidden('payment')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Member::firstWhere('last_name', 'Jonkman')->memberships()->count())->toBe(0);
});

it('does not show the first-membership section when editing', function () {
    $member = Member::factory()->create();

    Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
        ->assertDontSee('Eerste lidmaatschap')
        ->assertFormFieldDoesNotExist('add_membership');
});

it('always ends the first period on 31 December of the current year', function (string $today, string $start, string $end) {
    travelTo($today);

    [$startsAt, $endsAt] = CreateMemberAction::firstPeriod();

    expect($startsAt->toDateString())->toBe($start)
        ->and($endsAt->toDateString())->toBe($end);
})->with([
    'new year' => ['2027-01-01 09:00', '2027-01-01', '2027-12-31'],
    'last day' => ['2026-12-31 23:30', '2026-12-31', '2026-12-31'],
    'leap day' => ['2028-02-29 12:00', '2028-02-29', '2028-12-31'],
]);

it('uses Amsterdam time for "today"', function () {
    // 23:30 UTC on 31-12 is already 1 January in Amsterdam.
    travelTo(Carbon\Carbon::parse('2026-12-31 23:30', 'UTC'));

    expect(CreateMemberAction::firstPeriod()[0]->toDateString())->toBe('2027-01-01');
});

it('creates member and membership together or not at all', function () {
    Member::factory()->create(['member_number' => 5]);

    expect(fn () => app(CreateMemberAction::class)->handle(
        ['member_number' => 5, 'first_name' => 'Dubbel', 'last_name' => 'Nummer'],
        withMembership: true,
    ))->toThrow(QueryException::class);

    expect(Member::where('first_name', 'Dubbel')->exists())->toBeFalse();
});
