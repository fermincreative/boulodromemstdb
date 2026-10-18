<?php

use App\Actions\Members\CancelMember;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberNumber;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['has_email_authentication' => true]);
});

it('builds the full name with tussenvoegsel', function () {
    $member = Member::factory()->make(['first_name' => 'Jan', 'middle_name' => 'van der', 'last_name' => 'Berg']);

    expect($member->full_name)->toBe('Jan van der Berg');
});

it('gives new members the next member number and keeps existing ones', function () {
    Member::factory()->create(['member_number' => 260]);

    expect(Member::factory()->create()->member_number)->toBe(261)
        ->and(Member::factory()->create(['member_number' => 12])->member_number)->toBe(12);
});

it('lists active members and hides cancelled ones by default', function () {
    actingAs($this->admin);
    $active = Member::factory()->create();
    $cancelled = Member::factory()->cancelled()->create();

    Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$cancelled])
        ->filterTable('status', 'cancelled')
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$active]);
});

it('searches by name, e-mail and member number', function () {
    actingAs($this->admin);
    $jan = Member::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen', 'email' => 'jan@example.com', 'member_number' => 101]);
    $piet = Member::factory()->create(['first_name' => 'Piet', 'last_name' => 'Pieters', 'email' => 'piet@example.com', 'member_number' => 202]);

    Livewire::test(ListMembers::class)
        ->searchTable('Jansen')->assertCanSeeTableRecords([$jan])->assertCanNotSeeTableRecords([$piet])
        ->searchTable('piet@example')->assertCanSeeTableRecords([$piet])->assertCanNotSeeTableRecords([$jan])
        ->searchTable('202')->assertCanSeeTableRecords([$piet])->assertCanNotSeeTableRecords([$jan])
        ->searchTable(MemberNumber::format(101))->assertCanSeeTableRecords([$jan])->assertCanNotSeeTableRecords([$piet])
        ->searchTable('SBM-0101-00')->assertCanNotSeeTableRecords([$jan, $piet]);
});

it('filters on club, including members without a club', function () {
    actingAs($this->admin);
    $club = Club::firstWhere('name', 'Mosa Petanca');
    $inClub = Member::factory()->create(['club_id' => $club->id]);
    $noClub = Member::factory()->create(['club_id' => null]);

    Livewire::test(ListMembers::class)
        ->filterTable('club', [$club->id])
        ->assertCanSeeTableRecords([$inClub])->assertCanNotSeeTableRecords([$noClub])
        ->filterTable('club', ['none'])
        ->assertCanSeeTableRecords([$noClub])->assertCanNotSeeTableRecords([$inClub]);
});

it('lets admins create a member', function () {
    actingAs($this->admin);

    Livewire::test(CreateMember::class)
        ->fillForm([
            'first_name' => 'Marie',
            'last_name' => 'Claessens',
            'postal_code' => '3620',
            'country' => 'België',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Member::firstWhere('last_name', 'Claessens'))
        ->member_number->toBe(1)
        ->postal_code->toBe('3620');
});

it('allows two members to share an e-mail address', function () {
    Member::factory()->count(2)->create(['email' => 'familie@example.com']);

    expect(Member::where('email', 'familie@example.com')->count())->toBe(2);
});

it('lets treasurers and viewers look but not edit', function (string $role) {
    $user = User::factory()->{$role}()->create(['has_email_authentication' => true]);
    $member = Member::factory()->create();
    actingAs($user);

    get(MemberResource::getUrl('index'))->assertOk();
    get(MemberResource::getUrl('view', ['record' => $member]))->assertOk();
    get(MemberResource::getUrl('create'))->assertForbidden();
    get(MemberResource::getUrl('edit', ['record' => $member]))->assertForbidden();
})->with(['treasurer', 'viewer']);

it('cancels a member from the view page and logs it', function () {
    actingAs($this->admin);
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->callAction('cancel', ['cancelled_at' => '2026-12-31', 'reason' => 'Verhuisd'])
        ->assertHasNoActionErrors();

    expect($member->fresh()->cancelled_at->toDateString())->toBe('2026-12-31');

    $log = Activity::where('event', 'cancelled')->sole();
    expect($log->subject->is($member))->toBeTrue()
        ->and($log->causer->is($this->admin))->toBeTrue()
        ->and($log->properties['reason'])->toBe('Verhuisd');
});

it('does not cancel a member twice', function () {
    $member = Member::factory()->cancelled()->create();

    app(CancelMember::class)->handle($member);
})->throws(InvalidArgumentException::class);

it('can undo a cancellation', function () {
    actingAs($this->admin);
    $member = Member::factory()->cancelled()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertActionHidden('cancel')
        ->callAction('reinstate');

    expect($member->fresh()->isCancelled())->toBeFalse()
        ->and(Activity::where('event', 'reinstated')->exists())->toBeTrue();
});

it('hides the cancel action from viewers', function () {
    actingAs(User::factory()->create(['has_email_authentication' => true]));
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertActionHidden('cancel')
        ->assertActionHidden('edit');
});
