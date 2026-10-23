<?php

namespace App\Actions\Schedule;

use App\Enums\ScheduleSlotStatus;
use App\Models\ScheduleSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Repeated events. Every occurrence is a normal part of a day in the Rooster (so it can be changed on its own);
 * the occurrences share a series id, so later ones can be changed or removed together.
 *
 * A date where the event can't go (another event in that part of the day, an overlapping extra part of the day)
 * is skipped and reported, never overwritten.
 */
class ScheduleSeries
{
    /** How far ahead an event can be repeated. */
    public const MAX_MONTHS = 12;

    public function __construct(private SaveScheduleSlot $save) {}

    /**
     * Repeats the event in $first every $everyWeeks weeks up to and including $until.
     *
     * @return array{created: int, skipped: array<string, string>} Skipped dates (d-m-Y) with the reason.
     */
    public function repeat(ScheduleSlot $first, CarbonInterface $until, int $everyWeeks = 1): array
    {
        if (! $first->isEvent()) {
            throw new InvalidArgumentException('Alleen een evenement kan worden herhaald.');
        }

        if (! in_array($everyWeeks, [1, 2], true)) {
            throw new InvalidRepeat('Kies elke week of om de week.');
        }

        $start = CarbonImmutable::parse($first->date);
        $until = CarbonImmutable::parse($until)->startOfDay();

        if ($until->lt($start->addWeeks($everyWeeks))) {
            throw new InvalidRepeat('Kies een einddatum na de eerstvolgende herhaling ('.$start->addWeeks($everyWeeks)->format('d-m-Y').').');
        }

        if ($until->gt($start->addMonths(self::MAX_MONTHS))) {
            throw new InvalidRepeat('Herhalen kan tot maximaal '.self::MAX_MONTHS.' maanden vooruit.');
        }

        $settings = self::settingsOf($first);
        $seriesId = $first->series_id ?? (string) Str::uuid();
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($first, $start, $until, $everyWeeks, $settings, $seriesId, &$created, &$skipped) {
            activity()->withoutLogs(function () use ($first, $start, $until, $everyWeeks, $settings, $seriesId, &$created, &$skipped) {
                $first->forceFill(['series_id' => $seriesId])->save();

                for ($date = $start->addWeeks($everyWeeks); $date->lte($until); $date = $date->addWeeks($everyWeeks)) {
                    $existing = $first->period->isStandard()
                        ? ScheduleSlot::whereDate('date', $date)->where('period', $first->period)->first()
                        : null;

                    if ($existing?->isEvent()) {
                        $skipped[$date->format('d-m-Y')] = "er staat al een evenement ({$existing->event_name})";

                        continue;
                    }

                    try {
                        $slot = $this->save->apply($date, $first->period, $settings);
                    } catch (InvalidArgumentException $e) {
                        $skipped[$date->format('d-m-Y')] = lcfirst(rtrim($e->getMessage(), '.'));

                        continue;
                    }

                    $slot->forceFill(['series_id' => $seriesId])->save();
                    $created++;
                }
            });

            activity('schedule')
                ->performedOn($first)
                ->event('repeated')
                ->withProperties([
                    'slot' => ['date' => $first->date->toDateString(), 'period' => $first->period->value, 'times' => $first->times()],
                    'every_weeks' => $everyWeeks,
                    'until' => $until->toDateString(),
                    'created' => $created,
                    'skipped' => $skipped,
                ])
                ->log("Evenement \"{$first->event_name}\" herhaald ".($everyWeeks === 1 ? 'elke week' : 'om de week')
                    .' t/m '.$until->format('d-m-Y').": {$created} keer".($skipped ? ', '.count($skipped).' overgeslagen' : ''));
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Applies $settings to the later occurrences of $slot's series (changing them, or removing them when closed).
     *
     * @return array{changed: int, skipped: array<string, string>}
     */
    public function applyToFollowing(ScheduleSlot $slot, SlotSettings $settings): array
    {
        $changed = 0;
        $skipped = [];

        if (! $slot->isRepeated()) {
            return ['changed' => 0, 'skipped' => []];
        }

        $following = $slot->followingInSeries()->get();

        DB::transaction(function () use ($slot, $following, $settings, &$changed, &$skipped) {
            activity()->withoutLogs(function () use ($following, $settings, &$changed, &$skipped) {
                foreach ($following as $occurrence) {
                    try {
                        $this->save->apply($occurrence->date, $occurrence->period, $settings, $occurrence);
                        $changed++;
                    } catch (InvalidArgumentException $e) {
                        $skipped[$occurrence->date->format('d-m-Y')] = lcfirst(rtrim($e->getMessage(), '.'));
                    }
                }
            });

            activity('schedule')
                ->performedOn($slot)
                ->event('series_updated')
                ->withProperties([
                    'slot' => ['date' => $slot->date->toDateString(), 'period' => $slot->period->value, 'times' => $slot->times()],
                    'status' => $settings->status->value,
                    'changed' => $changed,
                    'skipped' => $skipped,
                ])
                ->log(($settings->status === ScheduleSlotStatus::Closed ? 'Volgende herhalingen verwijderd' : 'Volgende herhalingen gewijzigd').": {$changed}");
        });

        return ['changed' => $changed, 'skipped' => $skipped];
    }

    /**
     * The last date of $slot's series.
     */
    public static function lastDate(ScheduleSlot $slot): ?CarbonImmutable
    {
        $last = $slot->isRepeated() ? ScheduleSlot::where('series_id', $slot->series_id)->max('date') : null;

        return $last ? CarbonImmutable::parse($last) : null;
    }

    public static function settingsOf(ScheduleSlot $slot): SlotSettings
    {
        return new SlotSettings(
            status: $slot->status(),
            lanes: $slot->lanes,
            eventName: $slot->event_name,
            eventNamePublic: $slot->event_name_public,
            eventLanes: $slot->event_lanes,
            remainingLanesReservable: $slot->remaining_lanes_reservable,
            startsAt: $slot->starts_at,
            endsAt: $slot->ends_at,
        );
    }
}
