<?php

use App\Actions\Schedule\SaveScheduleSlot;
use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Filament\Pages\OpeningHoursSettings;
use App\Filament\Pages\Schedule;
use App\Models\ScheduleSlot;
use App\Models\User;
use App\Services\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Js;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0))); // a Thursday

it('shows the current week from Monday to Sunday, with each part of the day', function () {
    actingAs(User::factory()->viewer()->create());
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-22'), SchedulePeriod::Evening, ScheduleSlotStatus::Open, 12);
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Afternoon, ScheduleSlotStatus::Event, 16, 'Clubkampioenschap', 10, true);

    get(Schedule::getUrl())->assertOk();

    Livewire::test(Schedule::class)
        ->assertSee('Rooster week 43 (19 – 25 oktober 2026)')
        ->assertSeeInOrder(['maandag', '19-10-2026', 'dinsdag', 'woensdag', 'donderdag (vandaag)', 'vrijdag', 'zaterdag', 'zondag', '25-10-2026'])
        ->assertSee('Open · 12 banen')
        ->assertSee('Clubkampioenschap')
        ->assertSee('10 van 16 banen · 6 te reserveren')
        ->assertSee('Gesloten');
});

it('moves between weeks', function () {
    actingAs(User::factory()->viewer()->create());

    Livewire::test(Schedule::class)
        ->callAction('nextWeek')
        ->assertSet('week', '2026-10-26')
        ->assertSee('Rooster week 44 (26 oktober – 1 november 2026)')
        ->callAction('previousWeek')
        ->callAction('previousWeek')
        ->assertSee('Rooster week 42')
        ->callAction('thisWeek')
        ->assertSet('week', null);
});

it('jumps to the week of a chosen day', function () {
    actingAs(User::factory()->viewer()->create());

    Livewire::test(Schedule::class)
        ->callAction('goToDate', data: ['date' => '2026-12-17'])
        ->assertSet('week', '2026-12-14')
        ->assertSee('Rooster week 51 (14 – 20 december 2026)');
});

it('keeps the past read-only', function () {
    actingAs(User::factory()->admin()->create());
    travelTo(now()->setTime(18, 0)); // Thursday evening: the afternoon is over

    Livewire::test(Schedule::class)
        // Monday–Wednesday have no "+ Extra dagdeel"; Thursday to Sunday do.
        ->assertSeeHtml(Js::from(['date' => '2026-10-22', 'period' => 'custom']))
        ->assertDontSeeHtml(Js::from(['date' => '2026-10-21', 'period' => 'custom']))
        ->assertDontSeeHtml(Js::from(['date' => '2026-10-21', 'period' => 'evening']))
        ->assertDontSeeHtml(Js::from(['date' => '2026-10-22', 'period' => 'afternoon']))
        ->assertSeeHtml(Js::from(['date' => '2026-10-22', 'period' => 'evening']))
        ->callAction('editSlot', data: ['status' => 'open', 'lanes' => 16], arguments: ['date' => '2026-10-20', 'period' => 'evening'])
        ->assertHasActionErrors(['status']);

    expect(ScheduleSlot::count())->toBe(0);
});

it('takes extra parts of a day in 24-hour time, however they are typed', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'custom'])
        ->setActionData(['starts_at' => '930', 'ends_at' => '11.45'])
        ->assertActionDataSet(['starts_at' => '09:30', 'ends_at' => '11:45'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ScheduleSlot::sole()->times())->toBe('09:30–11:45');

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: ['status' => 'open', 'starts_at' => '2 PM', 'ends_at' => '16:00', 'lanes' => 16], arguments: ['date' => '2026-10-25', 'period' => 'custom'])
        ->assertHasActionErrors(['starts_at']);
});

it('opens the week of any date in the address', function () {
    actingAs(User::factory()->viewer()->create());

    Livewire::withQueryParams(['week' => '2026-12-31'])->test(Schedule::class)->assertSee('Rooster week 53 (28 december – 3 januari 2027)');
    Livewire::withQueryParams(['week' => 'onzin'])->test(Schedule::class)->assertSee('Rooster week 43');
});

it('lets an admin open a part of the day with fewer lanes', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-23', 'period' => 'afternoon'])
        ->assertActionDataSet(['status' => 'open', 'lanes' => 16])
        ->setActionData(['status' => 'open', 'lanes' => 10])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Rooster bijgewerkt')
        ->assertSee('Open · 10 banen');

    expect(ScheduleSlot::sole())
        ->date->toDateString()->toBe('2026-10-23')
        ->period->toBe(SchedulePeriod::Afternoon)
        ->lanes->toBe(10);
});

it('lets an admin plan an event', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: [
            'status' => 'event', 'lanes' => 16, 'event_name' => 'Besloten feest', 'event_lanes' => 16, 'remaining_lanes_reservable' => false,
        ], arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->assertHasNoActionErrors()
        ->assertSee('Besloten feest')
        ->assertSee('Alle 16 banen · niets te reserveren');

    expect(ScheduleSlot::sole()->reservableLanes())->toBe(0);
});

it('validates lanes in the form', function (array $data, string $field) {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: $data, arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->assertHasActionErrors([$field]);

    expect(ScheduleSlot::count())->toBe(0);
})->with([
    'more than 16' => [['status' => 'open', 'lanes' => 20], 'lanes'],
    'event without name' => [['status' => 'event', 'lanes' => 16, 'event_name' => '', 'event_lanes' => 4], 'event_name'],
    'event larger than opening' => [['status' => 'event', 'lanes' => 8, 'event_name' => 'Toernooi', 'event_lanes' => 10], 'event_lanes'],
]);

it('closes a part of the day again', function () {
    actingAs(User::factory()->admin()->create());
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Evening, ScheduleSlotStatus::Open);

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->assertActionDataSet(['status' => 'open'])
        ->setActionData(['status' => 'closed'])
        ->callMountedAction();

    expect(ScheduleSlot::count())->toBe(0);
});

it('starts an event with all lanes taken and nothing reservable', function () {
    actingAs(User::factory()->admin()->create());
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Evening, ScheduleSlotStatus::Open, 12);

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->assertActionDataSet(['lanes' => 12, 'event_lanes' => 12, 'remaining_lanes_reservable' => false])
        ->setActionData(['status' => 'event', 'event_name' => 'Competitie'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ScheduleSlot::sole())->event_lanes->toBe(12)->reservableLanes()->toBe(0);
});

it('lets an admin choose whether the event name is public', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->setActionData(['status' => 'event', 'event_name' => 'Besloten feest'])
        ->assertActionDataSet(['event_name_public' => false])
        ->callMountedAction()
        ->assertSee('Naam niet openbaar');

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'evening'])
        ->assertActionDataSet(['event_name' => 'Besloten feest', 'event_name_public' => false])
        ->setActionData(['event_name' => 'Open toernooi', 'event_name_public' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('Naam openbaar');

    expect(ScheduleSlot::sole()->publicEventName())->toBe('Open toernooi');
});

it('lets an admin repeat an event and shows how far it repeats', function () {
    actingAs(User::factory()->admin()->create());
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-11-07'), SchedulePeriod::Afternoon, ScheduleSlotStatus::Event, 16, 'Toernooi');

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'afternoon'])
        ->setActionData(['status' => 'event', 'event_name' => 'Jeugdtraining', 'repeat' => true, 'repeat_every' => 1, 'repeat_until' => '14-11-2026'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Rooster bijgewerkt')
        ->assertSee('↻ Herhaalt t/m 14-11-2026');

    expect(ScheduleSlot::where('event_name', 'Jeugdtraining')->count())->toBe(3);

    $occurrence = ScheduleSlot::where('event_name', 'Jeugdtraining')->whereDate('date', '2026-10-31')->sole();

    Livewire::test(Schedule::class)
        ->set('week', '2026-10-26')
        ->mountAction('editSlot', arguments: ['date' => '2026-10-31', 'period' => 'afternoon'])
        ->assertActionDataSet(['apply_to_following' => false])
        ->assertFormFieldExists('apply_to_following', 'mountedActionSchema0',
            fn ($field): bool => $field->getLabel() === 'Ook toepassen op de volgende herhalingen (1, t/m 14-11-2026)')
        ->setActionData(['status' => 'closed', 'apply_to_following' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ScheduleSlot::where('event_name', 'Jeugdtraining')->count())->toBe(1)
        ->and($occurrence->fresh())->toBeNull();
});

it('puts a repeat end date problem on the end date field', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: ['status' => 'event', 'lanes' => 16, 'event_name' => 'X', 'event_lanes' => 16, 'repeat' => true, 'repeat_every' => 2, 'repeat_until' => '30-10-2026'],
            arguments: ['date' => '2026-10-24', 'period' => 'afternoon'])
        ->assertHasActionErrors(['repeat_until']);

    expect(ScheduleSlot::count())->toBe(0);
});

it('lets an admin add an extra part of a day, and blocks an overlapping middag', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(Schedule::class)
        ->assertSee('+ Extra dagdeel')
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'custom'])
        ->assertActionDataSet(['status' => 'open'])
        ->setActionData(['starts_at' => '12:00', 'ends_at' => '14:00', 'lanes' => 6])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('12:00–14:00')
        ->assertSee('Niet mogelijk: overlapt met 12:00–14:00');

    $extra = ScheduleSlot::sole();
    expect($extra->period)->toBe(SchedulePeriod::Custom)->and($extra->lanes)->toBe(6);

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: ['status' => 'open', 'lanes' => 16], arguments: ['date' => '2026-10-24', 'period' => 'afternoon'])
        ->assertHasActionErrors(['status']);

    Livewire::test(Schedule::class)
        ->mountAction('editSlot', arguments: ['date' => '2026-10-24', 'period' => 'custom', 'id' => $extra->id])
        ->assertActionDataSet(['starts_at' => '12:00', 'lanes' => 6])
        ->setActionData(['status' => 'closed'])
        ->callMountedAction();

    expect(ScheduleSlot::count())->toBe(0);
});

it('shows an overlap with another extra part of a day on the time fields', function () {
    actingAs(User::factory()->admin()->create());
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '10:00', endsAt: '12:00');

    Livewire::test(Schedule::class)
        ->callAction('editSlot', data: ['status' => 'open', 'starts_at' => '11:00', 'ends_at' => '12:30', 'lanes' => 16], arguments: ['date' => '2026-10-24', 'period' => 'custom'])
        ->assertHasActionErrors(['starts_at']);
});

it('lets admins set the standard times', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(OpeningHoursSettings::class)
        ->assertSchemaStateSet(['afternoon_start' => '13:00', 'evening_end' => '23:00'])
        ->set('data.afternoon_start', '12:30')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Openingstijden opgeslagen');

    expect(OpeningHours::times(SchedulePeriod::Afternoon))->toBe(['12:30', '17:00']);

    Livewire::test(Schedule::class)->assertSee('Middag 12:30–17:00');
});

it('shows standard-times mistakes on the right field', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(OpeningHoursSettings::class)
        ->set('data.evening_start', '16:00')
        ->call('save')
        ->assertHasErrors(['data.evening_start']);
});

it('keeps the standard times for admins only', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    get(OpeningHoursSettings::getUrl())->assertForbidden();
})->with(['treasurer', 'viewer']);

it('is read-only for treasurers and viewers', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    Livewire::test(Schedule::class)
        ->assertDontSeeHtml("mountAction('editSlot'")
        ->assertDontSee('+ Extra dagdeel')
        ->assertActionHidden('editSlot');
})->with(['treasurer', 'viewer']);
