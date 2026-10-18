<?php

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Jobs\SendMembershipCard;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    $this->member = Member::factory()->create(['email' => 'lid@example.com']);
    $this->membership = Membership::factory()->for($this->member)->create();
});

function staff(string $role): User
{
    return User::factory()->{$role}()->create(['has_email_authentication' => true]);
}

it('downloads the phone and print card from the member page', function (string $action) {
    actingAs(staff('viewer'));

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->callAction($action)
        ->assertFileDownloaded();
})->with(['cardPhone', 'cardPrint']);

it('lets admins and treasurers send the card, not viewers', function (string $role, bool $allowed) {
    Queue::fake();
    actingAs(staff($role));

    $page = Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()]);

    if ($allowed) {
        $page->callAction('sendCard');
        Queue::assertPushed(SendMembershipCard::class);
    } else {
        $page->assertActionHidden('sendCard');
        Queue::assertNothingPushed();
    }
})->with([
    'admin' => ['admin', true],
    'treasurer' => ['treasurer', true],
    'viewer' => ['viewer', false],
]);

it('disables sending for members without e-mail', function () {
    $this->member->update(['email' => null]);
    actingAs(staff('admin'));

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->assertActionDisabled('sendCard')
        ->assertActionEnabled('cardPrint');
});

it('hides card actions when the member has no active membership', function () {
    $this->membership->update(['status' => 'pending']);
    actingAs(staff('admin'));

    Livewire::test(ViewMember::class, ['record' => $this->member->getRouteKey()])
        ->assertActionHidden('cardPhone')
        ->assertActionHidden('sendCard');
});

it('lets admins add a membership period', function () {
    actingAs(staff('admin'));

    Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => ViewMember::class])
        ->callAction(TestAction::make('create')->table(), [
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-12-31',
            'status' => 'pending',
        ])
        ->assertHasNoFormErrors();

    expect($this->member->memberships()->count())->toBe(2);
});

it('rejects a second period with the same start date', function () {
    actingAs(staff('admin'));

    Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => ViewMember::class])
        ->callAction(TestAction::make('create')->table(), [
            'starts_at' => $this->membership->starts_at->toDateString(),
            'ends_at' => '2030-12-31',
            'status' => 'active',
        ])
        ->assertHasFormErrors(['starts_at']);
});
