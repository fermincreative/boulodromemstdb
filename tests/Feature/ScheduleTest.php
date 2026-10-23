<?php

use App\Actions\Schedule\SaveOpeningHours;
use App\Actions\Schedule\SaveScheduleSlot;
use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Models\ScheduleSlot;
use App\Services\ActivityPresenter;
use App\Services\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0)));

function saveSlot(ScheduleSlotStatus $status, ...$args): ?ScheduleSlot
{
    return app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Afternoon, $status, ...$args);
}

it('opens a part of the day with all 16 lanes by default', function () {
    $slot = saveSlot(ScheduleSlotStatus::Open);

    expect($slot->lanes)->toBe(16)
        ->and($slot->status())->toBe(ScheduleSlotStatus::Open)
        ->and($slot->reservableLanes())->toBe(16)
        ->and($slot->times())->toBe('13:00–17:00');
});

it('lets an event take all lanes and block the rest by default', function () {
    $slot = saveSlot(ScheduleSlotStatus::Event, 16, 'Toernooi');

    expect($slot->event_lanes)->toBe(16)
        ->and($slot->remaining_lanes_reservable)->toBeFalse()
        ->and($slot->reservableLanes())->toBe(0);

    $slot = saveSlot(ScheduleSlotStatus::Event, 16, 'Toernooi', 6);
    expect($slot->reservableLanes())->toBe(0);
});

it('keeps event names private unless made public', function () {
    $private = saveSlot(ScheduleSlotStatus::Event, 16, 'Besloten feest Jansen');

    expect($private->event_name_public)->toBeFalse()
        ->and($private->publicEventName())->toBe('Evenement')
        ->and($private->event_name)->toBe('Besloten feest Jansen');

    $public = app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Evening, ScheduleSlotStatus::Event,
        16, 'Clubkampioenschap', eventNamePublic: true);

    expect($public->publicEventName())->toBe('Clubkampioenschap')
        ->and(saveSlot(ScheduleSlotStatus::Open)->publicEventName())->toBeNull();
});

it('forgets that the name was public when the event is removed', function () {
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Afternoon, ScheduleSlotStatus::Event, 16, 'Toernooi', eventNamePublic: true);

    expect(saveSlot(ScheduleSlotStatus::Open)->event_name_public)->toBeFalse();
});

describe('extra parts of a day', function () {
    function saveExtra(string $from, string $until, ?ScheduleSlot $slot = null, ScheduleSlotStatus $status = ScheduleSlotStatus::Open): ?ScheduleSlot
    {
        return app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Custom, $status,
            startsAt: $from, endsAt: $until, slot: $slot);
    }

    it('can be added with their own times, several per day', function () {
        saveExtra('09:00', '11:00');
        $slot = saveExtra('11:00', '12:30');

        expect(ScheduleSlot::where('period', SchedulePeriod::Custom)->count())->toBe(2)
            ->and($slot->times())->toBe('11:00–12:30')
            ->and($slot->lanes)->toBe(16);
    });

    it('rules out middag when it overlaps, and the other way round', function () {
        saveExtra('12:00', '14:00');

        expect(fn () => saveSlot(ScheduleSlotStatus::Open))->toThrow(InvalidArgumentException::class, 'Overlapt met extra dagdeel 12:00–14:00 op deze dag.');

        app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Evening, ScheduleSlotStatus::Open);
        expect(fn () => saveExtra('22:00', '23:30'))->toThrow(InvalidArgumentException::class, 'Overlapt met avond 19:00–23:00 op deze dag.');
    });

    it('may touch other parts of the day', function () {
        saveSlot(ScheduleSlotStatus::Open);

        expect(saveExtra('10:00', '13:00'))->not->toBeNull()
            ->and(saveExtra('17:00', '19:00'))->not->toBeNull();
    });

    it('can be moved, without clashing with itself', function () {
        $slot = saveExtra('09:00', '11:00');

        expect(saveExtra('09:30', '11:30', $slot)->times())->toBe('09:30–11:30')
            ->and(ScheduleSlot::count())->toBe(1);
    });

    it('needs an end after the start', function (string $from, string $until, string $message) {
        expect(fn () => saveExtra($from, $until))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'reversed' => ['14:00', '12:00', 'De eindtijd moet na de begintijd liggen.'],
        'empty' => ['', '12:00', 'Vul een begin- en eindtijd in.'],
        'nonsense' => ['25:00', '26:00', 'Vul een begin- en eindtijd in.'],
    ]);

    it('is removed when closed', function () {
        $slot = saveExtra('09:00', '11:00');
        saveExtra('09:00', '11:00', $slot, ScheduleSlotStatus::Closed);

        expect(ScheduleSlot::count())->toBe(0);
    });
});

describe('the past', function () {
    function saveOn(string $date, SchedulePeriod $period, ScheduleSlotStatus $status = ScheduleSlotStatus::Open, ...$args): ?ScheduleSlot
    {
        return app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse($date), $period, $status, ...$args);
    }

    it('cannot open anything on an earlier day', function () {
        expect(fn () => saveOn('2026-10-21', SchedulePeriod::Evening))
            ->toThrow(InvalidArgumentException::class, 'Dit dagdeel ligt in het verleden en kan niet meer worden gewijzigd.');
    });

    it('cannot change or close what happened on an earlier day', function () {
        $slot = ScheduleSlot::create(['date' => '2026-10-21', 'period' => SchedulePeriod::Evening, 'starts_at' => '19:00', 'ends_at' => '23:00', 'lanes' => 16]);

        expect(fn () => saveOn('2026-10-21', SchedulePeriod::Evening, ScheduleSlotStatus::Closed))->toThrow(InvalidArgumentException::class)
            ->and(fn () => saveOn('2026-10-21', SchedulePeriod::Evening, ScheduleSlotStatus::Open, 8))->toThrow(InvalidArgumentException::class)
            ->and($slot->fresh()->lanes)->toBe(16);
    });

    it('allows today what has not ended yet', function () {
        // 12:00 now: the morning is over, the afternoon and evening are still to come.
        expect(saveOn('2026-10-22', SchedulePeriod::Afternoon))->not->toBeNull()
            ->and(app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-22'), SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '11:00', endsAt: '12:30'))->not->toBeNull()
            ->and(fn () => app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-22'), SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '09:00', endsAt: '11:00'))
            ->toThrow(InvalidArgumentException::class, 'Dit dagdeel is vandaag om 11:00 al afgelopen.');
    });

    it('locks a part of today once it has ended', function () {
        saveOn('2026-10-22', SchedulePeriod::Afternoon);
        travelTo(now()->setTime(17, 0));

        expect(fn () => saveOn('2026-10-22', SchedulePeriod::Afternoon, ScheduleSlotStatus::Closed))->toThrow(InvalidArgumentException::class)
            ->and(SaveScheduleSlot::hasEnded(CarbonImmutable::parse('2026-10-22'), '17:00'))->toBeTrue()
            ->and(SaveScheduleSlot::hasEnded(CarbonImmutable::parse('2026-10-22'), '23:00'))->toBeFalse();
    });
});

it('reads typed times as 24-hour times', function (string $typed, ?string $expected) {
    expect(OpeningHours::normalize($typed))->toBe($expected);
})->with([
    ['13:00', '13:00'], ['13:00:00', '13:00'], ['9:30', '09:30'], ['9.30', '09:30'], ['1330', '13:30'], ['930', '09:30'],
    ['9', '09:00'], ['23:59', '23:59'], ['24:00', null], ['12:60', null], ['1 PM', null], ['', null],
]);

describe('opening hours', function () {
    it('opens middag and avond with the standard times, and keeps them when the standard changes', function () {
        $old = saveSlot(ScheduleSlotStatus::Open);

        app(SaveOpeningHours::class)->handle('13:30', '17:30', '19:30', '23:00');
        $new = app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-25'), SchedulePeriod::Afternoon, ScheduleSlotStatus::Open);

        expect($old->fresh()->times())->toBe('13:00–17:00')
            ->and($new->times())->toBe('13:30–17:30')
            ->and(OpeningHours::times(SchedulePeriod::Evening))->toBe(['19:30', '23:00']);
    });

    it('rejects impossible standard times', function (array $times, string $field) {
        try {
            app(SaveOpeningHours::class)->handle(...$times);
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }
    })->with([
        'middag reversed' => [['17:00', '13:00', '19:00', '23:00'], 'afternoon_end'],
        'avond before middag ends' => [['13:00', '17:00', '16:00', '23:00'], 'evening_start'],
        'nonsense' => [['13:00', '17:00', 'x', '23:00'], 'evening_start'],
    ]);
});

it('keeps one entry per day and part of the day', function () {
    saveSlot(ScheduleSlotStatus::Open, 12);
    saveSlot(ScheduleSlotStatus::Open, 10);
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Evening, ScheduleSlotStatus::Open);

    expect(ScheduleSlot::count())->toBe(2)
        ->and(ScheduleSlot::where('period', SchedulePeriod::Afternoon)->sole()->lanes)->toBe(10);
});

it('works out the lanes left for reservations around an event', function (bool $reservable, int $expected) {
    $slot = saveSlot(ScheduleSlotStatus::Event, 14, 'Clubkampioenschap', 6, $reservable);

    expect($slot->status())->toBe(ScheduleSlotStatus::Event)
        ->and($slot->event_name)->toBe('Clubkampioenschap')
        ->and($slot->reservableLanes())->toBe($expected);
})->with([
    'rest reservable' => [true, 8],
    'rest not reservable' => [false, 0],
]);

it('forgets the event when the slot becomes a normal opening again', function () {
    saveSlot(ScheduleSlotStatus::Event, 16, 'Toernooi', 16, false);
    $slot = saveSlot(ScheduleSlotStatus::Open, 16);

    expect($slot->event_name)->toBeNull()
        ->and($slot->event_lanes)->toBeNull()
        ->and($slot->remaining_lanes_reservable)->toBeTrue()
        ->and($slot->reservableLanes())->toBe(16);
});

it('closes a part of the day by removing it', function () {
    saveSlot(ScheduleSlotStatus::Open);

    expect(saveSlot(ScheduleSlotStatus::Closed))->toBeNull()
        ->and(ScheduleSlot::count())->toBe(0);
});

it('rejects impossible lane numbers and events without a name', function (array $args, string $message) {
    expect(fn () => saveSlot(...$args))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'too many lanes' => [[ScheduleSlotStatus::Open, 17], 'Kies tussen 1 en 16 banen.'],
    'no lanes' => [[ScheduleSlotStatus::Open, 0], 'Kies tussen 1 en 16 banen.'],
    'event without name' => [[ScheduleSlotStatus::Event, 16, ' ', 4], 'Vul de naam van het evenement in.'],
    'event bigger than the opening' => [[ScheduleSlotStatus::Event, 10, 'Toernooi', 12], 'Het evenement gebruikt tussen 1 en 10 banen.'],
]);

it('names extra parts of a day by their times in the log', function () {
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-10-24'), SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '10:00', endsAt: '12:00');

    expect(app(ActivityPresenter::class)->description(Activity::where('log_name', 'schedule')->sole()))
        ->toBe('Rooster: extra dagdeel 10:00–12:00 24-10-2026 open');
});

it('logs changes in Dutch, also after closing', function () {
    saveSlot(ScheduleSlotStatus::Open);
    saveSlot(ScheduleSlotStatus::Open, 8);
    saveSlot(ScheduleSlotStatus::Closed);

    $presenter = app(ActivityPresenter::class);
    expect(Activity::where('log_name', 'schedule')->oldest('id')->get()->map(fn ($a) => $presenter->description($a))->all())->toBe([
        'Rooster: middag 24-10-2026 open',
        'Rooster gewijzigd: middag 24-10-2026: banen',
        'Rooster: middag 24-10-2026 gesloten',
    ]);
});
