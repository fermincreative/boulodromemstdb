<?php

namespace App\Actions\Schedule;

use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Models\ScheduleSlot;
use App\Services\OpeningHours;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Sets one part of a day in the Rooster: closed, open with a number of lanes, or open with an event.
 *
 * - Middag and Avond take their times from Beheer → Openingstijden when they are opened; the times are kept with the slot.
 * - Extra parts of a day ("custom") have their own times; there can be several per day.
 * - Parts of a day never overlap, so an extra 12:00–14:00 rules out a 13:00–17:00 middag on that day (and the other way round).
 * - An event takes all lanes and blocks the rest for reservations unless the admin says otherwise.
 * - The past is fixed: nothing can be opened, changed or closed on an earlier day, or for a part of today that has ended.
 */
class SaveScheduleSlot
{
    public static function maxLanes(): int
    {
        return (int) config('schedule.lanes');
    }

    /**
     * @param  ScheduleSlot|null  $slot  The extra part of a day being edited (null: a new one). Middag/Avond are found by date.
     * @return ScheduleSlot|null The slot, or null when it is closed (removed).
     */
    public function handle(
        CarbonInterface $date,
        SchedulePeriod $period,
        ScheduleSlotStatus $status,
        ?int $lanes = null,
        ?string $eventName = null,
        ?int $eventLanes = null,
        bool $remainingLanesReservable = false,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?ScheduleSlot $slot = null,
        bool $eventNamePublic = false,
    ): ?ScheduleSlot {
        $slot = $period->isStandard()
            ? ScheduleSlot::whereDate('date', $date)->where('period', $period)->first()
            : $slot;

        if ($slot && ! $slot->date->isSameDay($date)) {
            throw new InvalidArgumentException('Dit dagdeel hoort bij een andere dag.');
        }

        if ($date->lt(CarbonImmutable::today()) || ($slot && self::hasEnded($date, $slot->ends_at))) {
            throw new InvalidArgumentException('Dit dagdeel ligt in het verleden en kan niet meer worden gewijzigd.');
        }

        if ($status === ScheduleSlotStatus::Closed) {
            $slot?->delete();

            return null;
        }

        [$startsAt, $endsAt] = $this->times($period, $slot, $startsAt, $endsAt);

        if (self::hasEnded($date, $endsAt)) {
            throw new InvalidArgumentException("Dit dagdeel is vandaag om {$endsAt} al afgelopen.");
        }

        if ($other = self::overlapping($date, $startsAt, $endsAt, except: $slot)) {
            throw new InvalidArgumentException('Overlapt met '.mb_strtolower($other->period->getLabel())." {$other->times()} op deze dag.");
        }

        $lanes ??= self::maxLanes();

        if ($lanes < 1 || $lanes > self::maxLanes()) {
            throw new InvalidArgumentException('Kies tussen 1 en '.self::maxLanes().' banen.');
        }

        $isEvent = $status === ScheduleSlotStatus::Event;
        $eventLanes ??= $lanes;

        if ($isEvent && blank($eventName)) {
            throw new InvalidArgumentException('Vul de naam van het evenement in.');
        }

        if ($isEvent && ($eventLanes < 1 || $eventLanes > $lanes)) {
            throw new InvalidArgumentException("Het evenement gebruikt tussen 1 en {$lanes} banen.");
        }

        $slot ??= new ScheduleSlot(['date' => $date->toDateString(), 'period' => $period]);

        $slot->fill([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'lanes' => $lanes,
            'event_name' => $isEvent ? trim((string) $eventName) : null,
            // Event names are private unless the admin makes them public.
            'event_name_public' => $isEvent && $eventNamePublic,
            'event_lanes' => $isEvent ? $eventLanes : null,
            // Without an event every lane is reservable; the switch only matters for events.
            'remaining_lanes_reservable' => $isEvent ? $remainingLanesReservable : true,
            // A repeated event that becomes a normal opening on this date leaves the series.
            'series_id' => $isEvent ? $slot->series_id : null,
        ])->save();

        return $slot;
    }

    /**
     * A part of a day that ends at $endsAt on $date is over (earlier day, or today and the end time has passed).
     */
    public static function hasEnded(CarbonInterface $date, string $endsAt): bool
    {
        $today = CarbonImmutable::today();

        return $date->lt($today) || ($date->isSameDay($today) && $endsAt <= now()->format('H:i'));
    }

    /**
     * The same, with the choices bundled (used to apply them to several dates).
     */
    public function apply(CarbonInterface $date, SchedulePeriod $period, SlotSettings $settings, ?ScheduleSlot $slot = null): ?ScheduleSlot
    {
        return $this->handle(
            $date,
            $period,
            $settings->status,
            $settings->lanes,
            $settings->eventName,
            $settings->eventLanes,
            $settings->remainingLanesReservable,
            $settings->startsAt,
            $settings->endsAt,
            $slot,
            $settings->eventNamePublic,
        );
    }

    /**
     * The part of a day on $date that shares time with $startsAt–$endsAt, if any.
     */
    public static function overlapping(CarbonInterface $date, string $startsAt, string $endsAt, ?ScheduleSlot $except = null): ?ScheduleSlot
    {
        return ScheduleSlot::whereDate('date', $date)
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function times(SchedulePeriod $period, ?ScheduleSlot $slot, ?string $startsAt, ?string $endsAt): array
    {
        if ($period->isStandard()) {
            // An open middag/avond keeps the times it was opened with.
            return $slot ? [$slot->starts_at, $slot->ends_at] : OpeningHours::times($period);
        }

        $startsAt = OpeningHours::normalize($startsAt);
        $endsAt = OpeningHours::normalize($endsAt);

        if (! $startsAt || ! $endsAt) {
            throw new InvalidArgumentException('Vul een begin- en eindtijd in.');
        }

        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('De eindtijd moet na de begintijd liggen.');
        }

        return [$startsAt, $endsAt];
    }
}
