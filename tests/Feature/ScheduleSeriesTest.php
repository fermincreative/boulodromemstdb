<?php

use App\Actions\Schedule\EditScheduleSlot;
use App\Actions\Schedule\InvalidRepeat;
use App\Actions\Schedule\SaveScheduleSlot;
use App\Actions\Schedule\ScheduleSeries;
use App\Actions\Schedule\SlotSettings;
use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Models\ScheduleSlot;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0))); // Thursday

function competition(string $date = '2026-10-27', SchedulePeriod $period = SchedulePeriod::Evening, array $overrides = []): ScheduleSlot
{
    return app(SaveScheduleSlot::class)->apply(CarbonImmutable::parse($date), $period, new SlotSettings(...[
        'status' => ScheduleSlotStatus::Event,
        'lanes' => 16,
        'eventName' => 'Competitie',
        'eventLanes' => 12,
        'remainingLanesReservable' => true,
        ...$overrides,
    ]));
}

function repeatUntil(ScheduleSlot $slot, string $until, int $every = 1): array
{
    return app(ScheduleSeries::class)->repeat($slot, CarbonImmutable::parse($until), $every);
}

it('repeats an event every week up to and including the end date', function () {
    $first = competition(); // Tuesday 27-10

    expect(repeatUntil($first, '2026-11-24'))->toBe(['created' => 4, 'skipped' => []]);

    $series = ScheduleSlot::where('series_id', $first->fresh()->series_id)->orderBy('date')->get();
    expect($series->map(fn ($s) => $s->date->format('d-m'))->all())->toBe(['27-10', '03-11', '10-11', '17-11', '24-11'])
        ->and($series->every(fn ($s) => $s->event_name === 'Competitie' && $s->event_lanes === 12 && $s->reservableLanes() === 4 && $s->times() === '19:00–23:00'))->toBeTrue();
});

it('repeats every other week', function () {
    $first = competition();
    repeatUntil($first, '2026-12-08', every: 2);

    expect(ScheduleSlot::orderBy('date')->get()->map(fn ($s) => $s->date->format('d-m'))->all())->toBe(['27-10', '10-11', '24-11', '08-12']);
});

it('repeats an extra part of a day with its own times', function () {
    $first = competition(period: SchedulePeriod::Custom, overrides: ['startsAt' => '10:00', 'endsAt' => '12:00']);
    repeatUntil($first, '2026-11-10');

    expect(ScheduleSlot::count())->toBe(3)
        ->and(ScheduleSlot::pluck('starts_at')->unique()->all())->toBe(['10:00']);
});

it('skips dates that already have an event or an overlapping part of the day, and turns a plain opening into the event', function () {
    competition('2026-11-03', overrides: ['eventName' => 'Clubkampioenschap']);
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-11-10'), SchedulePeriod::Custom, ScheduleSlotStatus::Open, startsAt: '18:00', endsAt: '20:00');
    app(SaveScheduleSlot::class)->handle(CarbonImmutable::parse('2026-11-17'), SchedulePeriod::Evening, ScheduleSlotStatus::Open, 10);

    $result = repeatUntil(competition(), '2026-11-17');

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe([
            '03-11-2026' => 'er staat al een evenement (Clubkampioenschap)',
            '10-11-2026' => 'overlapt met extra dagdeel 18:00–20:00 op deze dag',
        ])
        ->and(ScheduleSlot::whereDate('date', '2026-11-03')->sole()->event_name)->toBe('Clubkampioenschap')
        ->and(ScheduleSlot::whereDate('date', '2026-11-17')->sole()->event_name)->toBe('Competitie');
});

it('keeps the end date within reach', function (string $until, string $message) {
    expect(fn () => repeatUntil(competition(), $until))->toThrow(InvalidRepeat::class, $message);
})->with([
    'before the next week' => ['2026-10-30', 'Kies een einddatum na de eerstvolgende herhaling (03-11-2026).'],
    'more than a year ahead' => ['2027-11-30', 'Herhalen kan tot maximaal 12 maanden vooruit.'],
]);

it('logs a repeat as one entry', function () {
    $first = competition();
    $before = Activity::count();

    repeatUntil($first, '2026-12-29');

    expect(Activity::count() - $before)->toBe(1)
        ->and(Activity::latest('id')->first()->description)->toBe('Evenement "Competitie" herhaald elke week t/m 29-12-2026: 9 keer');
});

describe('changing a repeated event', function () {
    function editOccurrence(ScheduleSlot $slot, array $settings, bool $following): array
    {
        return app(EditScheduleSlot::class)->handle($slot->date, $slot->period, new SlotSettings(...$settings), $slot, applyToFollowing: $following);
    }

    beforeEach(function () {
        $this->first = competition();
        repeatUntil($this->first, '2026-11-24');
        $this->third = ScheduleSlot::whereDate('date', '2026-11-10')->sole();
    });

    it('changes only this date by default', function () {
        editOccurrence($this->third, ['status' => ScheduleSlotStatus::Event, 'lanes' => 16, 'eventName' => 'Competitie (finale)', 'eventLanes' => 16], following: false);

        expect(ScheduleSlot::whereDate('date', '2026-11-10')->sole()->event_name)->toBe('Competitie (finale)')
            ->and(ScheduleSlot::whereDate('date', '2026-11-17')->sole()->event_name)->toBe('Competitie');
    });

    it('can change this and the later dates, leaving earlier ones alone', function () {
        $result = editOccurrence($this->third, ['status' => ScheduleSlotStatus::Event, 'lanes' => 14, 'eventName' => 'Wintercompetitie', 'eventLanes' => 14], following: true);

        expect($result['changed'])->toBe(2)
            ->and(ScheduleSlot::orderBy('date')->pluck('event_name')->all())
            ->toBe(['Competitie', 'Competitie', 'Wintercompetitie', 'Wintercompetitie', 'Wintercompetitie']);
    });

    it('can end the series from a date on', function () {
        editOccurrence($this->third, ['status' => ScheduleSlotStatus::Closed], following: true);

        expect(ScheduleSlot::orderBy('date')->get()->map(fn ($s) => $s->date->format('d-m'))->all())->toBe(['27-10', '03-11'])
            ->and(ScheduleSeries::lastDate($this->first->fresh())->format('d-m-Y'))->toBe('03-11-2026');
    });

    it('takes a date out of the series when it becomes a normal opening', function () {
        editOccurrence($this->third, ['status' => ScheduleSlotStatus::Open, 'lanes' => 16], following: false);

        expect(ScheduleSlot::whereDate('date', '2026-11-10')->sole()->isRepeated())->toBeFalse()
            ->and(ScheduleSlot::whereNotNull('series_id')->count())->toBe(4);
    });

    it('does not touch dates that have passed', function () {
        travelTo(now()->setDate(2026, 11, 12));

        expect(fn () => editOccurrence($this->third, ['status' => ScheduleSlotStatus::Closed], following: true))->toThrow(InvalidArgumentException::class)
            ->and(ScheduleSlot::count())->toBe(5);
    });
});
