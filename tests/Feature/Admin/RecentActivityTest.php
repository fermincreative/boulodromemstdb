<?php

use App\Actions\Members\CancelMember;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Widgets\RecentActivity;
use App\Jobs\SendMembershipCard;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\ActivityPresenter;
use App\Support\MemberNumber;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    $this->admin = User::factory()->admin()->create(['name' => 'Florian']);
    actingAs($this->admin);
    $this->member = Member::factory()->create(['first_name' => 'Mees', 'middle_name' => null, 'last_name' => 'Jansen', 'member_number' => 42, 'email' => 'mees@example.com']);
});

function describeLatest(): string
{
    return app(ActivityPresenter::class)->description(Activity::latest('id')->first());
}

it('describes member edits in Dutch, naming fields but not their values', function () {
    $this->member->update(['street' => 'Geheimstraat 1', 'email' => 'nieuw@example.com']);

    expect(describeLatest())->toBe('Lidgegevens gewijzigd: e-mailadres, adres')
        ->not->toContain('Geheimstraat');
});

it('describes new members, cancellations and memberships', function () {
    expect(app(ActivityPresenter::class)->description(Activity::where('event', 'created')->first()))->toBe('Lid toegevoegd');

    app(CancelMember::class)->handle($this->member);
    expect(describeLatest())->toBe('Lidmaatschap opgezegd');

    Membership::factory()->forYear(2026)->for($this->member)->create();
    expect(describeLatest())->toBe('Lidmaatschap toegevoegd (2026)');
});

it('records who sent a card, even though the queue sends it', function () {
    Mail::fake();
    $membership = Membership::factory()->for($this->member)->create();

    (new SendMembershipCard($membership, sentById: $this->admin->id))->handle();

    $log = Activity::where('event', 'card_sent')->sole();
    $presenter = app(ActivityPresenter::class);

    expect($presenter->description($log))->toBe("Lidmaatschapskaart {$membership->cardYear()} verstuurd naar mees@example.com")
        ->and($presenter->causerName($log))->toBe('Florian')
        ->and($presenter->memberLabel($log))->toBe('Mees Jansen (SBM-0042-'.MemberNumber::checkDigits(42).')');
});

it('shows recent events on the dashboard, newest first', function () {
    $this->member->update(['city' => 'Maastricht-Oost']);

    Livewire::test(RecentActivity::class)
        ->assertSee('Recente gebeurtenissen')
        ->assertSee('Lidgegevens gewijzigd: woonplaats')
        ->assertSee('Mees Jansen (SBM-0042-')
        ->assertDontSee('Maastricht-Oost')
        ->assertCanSeeTableRecords(Activity::latest('id')->get(), inOrder: true);
});

it('puts the widget on the dashboard', function () {
    get('/admin')->assertOk()->assertSeeLivewire(RecentActivity::class);
});

it('filters the full log by part of the system', function () {
    Membership::factory()->for($this->member)->create();

    Livewire::test(ListActivities::class)
        ->filterTable('log_name', 'memberships')
        ->assertCanSeeTableRecords(Activity::where('log_name', 'memberships')->get())
        ->assertCanNotSeeTableRecords(Activity::where('log_name', 'members')->get());
});

it('lets every role read the log', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    get(ActivityResource::getUrl())->assertOk();
})->with(['treasurer', 'viewer']);
