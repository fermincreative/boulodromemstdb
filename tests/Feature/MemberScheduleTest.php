<?php

use App\Actions\Schedule\SaveScheduleSlot;
use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0)); // Thursday, week 43
    $member = Member::factory()->create(['email' => 'jan@example.nl']);
    Membership::factory()->forYear(2026)->for($member)->create();
    $this->account = $member->fresh()->account;
});

function openSlot(string $date, SchedulePeriod $period, ScheduleSlotStatus $status = ScheduleSlotStatus::Open, ...$args): void
{
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse($date), $period, $status, ...$args);
}

it('shows this week\'s openings to logged-in members', function () {
    openSlot('2026-10-22', SchedulePeriod::Evening, ScheduleSlotStatus::Open, 12);
    openSlot('2026-10-24', SchedulePeriod::Afternoon);
    actingAs($this->account, 'member');

    get('/rooster')
        ->assertOk()
        ->assertSee('Week 43')
        ->assertSee('19 oktober t/m 25 oktober 2026')
        ->assertSeeInOrder(['maandag', 'Gesloten', 'donderdag (vandaag)', 'Avond', '19:00–23:00', 'Open · 12 banen', 'zaterdag', 'Middag', '13:00–17:00', 'Open · 16 banen', 'zondag', 'Gesloten'])
        ->assertSee('Rooster')
        ->assertSee('Mijn lidmaatschap');
});

it('shows a private event only as "Evenement", and whether lanes are free', function () {
    openSlot('2026-10-23', SchedulePeriod::Evening, ScheduleSlotStatus::Event, 16, 'Besloten feest Jansen', 16, false);
    openSlot('2026-10-24', SchedulePeriod::Evening, ScheduleSlotStatus::Event, 16, 'Clubkampioenschap', 10, true, eventNamePublic: true);
    actingAs($this->account, 'member');

    get('/rooster')
        ->assertDontSee('Besloten feest Jansen')
        ->assertSeeInOrder(['vrijdag', 'Evenement', 'geen vrije banen', 'zaterdag', 'Clubkampioenschap', '6 banen vrij']);
});

it('shows extra parts of a day with their own times', function () {
    openSlot('2026-10-25', SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '10:00', endsAt: '12:00');
    actingAs($this->account, 'member');

    get('/rooster')->assertSeeInOrder(['zondag', 'Extra', '10:00–12:00']);
});

it('moves between weeks, but not back before this week', function () {
    openSlot('2026-11-03', SchedulePeriod::Evening);
    actingAs($this->account, 'member');

    get('/rooster')->assertDontSee('Vorige week')->assertSee('?week=2026-10-26', false);
    get('/rooster?week=2026-11-04')->assertSee('Week 45')->assertSee('Open · 16 banen')->assertSee('Vorige week')->assertSee('Deze week');
    get('/rooster?week=2026-01-05')->assertSee('Week 43');
    get('/rooster?week=onzin')->assertSee('Week 43');
});

it('is for members only, and linked from the overview', function () {
    get('/rooster')->assertRedirect('/');

    actingAs($this->account, 'member');
    get('/')->assertSee(route('portal.schedule'), false);
});

it('shows the coming days on the overview, below the QR code, with a button to the full Rooster', function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    openSlot('2026-10-22', SchedulePeriod::Evening);
    openSlot('2026-10-25', SchedulePeriod::Afternoon, ScheduleSlotStatus::Event, 16, 'Toernooi', eventNamePublic: true);
    openSlot('2026-10-26', SchedulePeriod::Evening, ScheduleSlotStatus::Open, 8); // the fifth day: not on the overview
    actingAs($this->account, 'member');

    get('/')
        ->assertSeeInOrder(['Toon deze code bij de ingang', 'Lidmaatschapskaart 2026 · geldig t/m 31-12-2026', 'Rooster komende dagen',
            'donderdag (vandaag)', 'Open · 16 banen', 'vrijdag', 'Gesloten', 'zaterdag', 'zondag', 'Toernooi', 'Bekijk het volledige rooster', 'Uw gegevens'])
        ->assertDontSee('Open · 8 banen')
        ->assertDontSee('maandag');
});
