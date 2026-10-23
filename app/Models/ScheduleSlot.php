<?php

namespace App\Models;

use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Services\OpeningHours;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A part of a day (middag, avond or an extra one with its own times) the Boulodrôme is open, with the lanes
 * available, and optionally an event that takes some of those lanes. The times are stored per part of the day.
 */
#[Fillable(['date', 'period', 'starts_at', 'ends_at', 'lanes', 'event_name', 'event_name_public', 'event_lanes', 'remaining_lanes_reservable', 'series_id'])]
class ScheduleSlot extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'period' => SchedulePeriod::class,
            'lanes' => 'integer',
            'event_name_public' => 'boolean',
            'event_lanes' => 'integer',
            'remaining_lanes_reservable' => 'boolean',
        ];
    }

    /**
     * "13:00–17:00"
     */
    public function times(): string
    {
        return OpeningHours::format($this->starts_at, $this->ends_at);
    }

    /**
     * Shares time with $startsAt–$endsAt (touching, e.g. 12:00–13:00 and 13:00–17:00, is fine).
     */
    public function overlaps(string $startsAt, string $endsAt): bool
    {
        return $this->starts_at < $endsAt && $this->ends_at > $startsAt;
    }

    public function status(): ScheduleSlotStatus
    {
        return $this->event_name !== null ? ScheduleSlotStatus::Event : ScheduleSlotStatus::Open;
    }

    public function isEvent(): bool
    {
        return $this->status() === ScheduleSlotStatus::Event;
    }

    /**
     * Part of a repeated event.
     */
    public function isRepeated(): bool
    {
        return $this->series_id !== null;
    }

    /**
     * The later occurrences of the same repeated event.
     *
     * @return Builder<ScheduleSlot>
     */
    public function followingInSeries(): Builder
    {
        return static::query()
            ->where('series_id', $this->series_id)
            ->whereDate('date', '>', $this->date)
            ->orderBy('date');
    }

    /**
     * The event as others may see it (members, public pages): its name only when marked public, otherwise "Evenement".
     * The admin Rooster always shows the real name.
     */
    public function publicEventName(): ?string
    {
        if (! $this->isEvent()) {
            return null;
        }

        return $this->event_name_public ? $this->event_name : ScheduleSlotStatus::Event->getLabel();
    }

    /**
     * Lanes left for reservations: all open lanes, or with an event the lanes it leaves free (if those may be reserved).
     */
    public function reservableLanes(): int
    {
        if (! $this->isEvent()) {
            return $this->lanes;
        }

        return $this->remaining_lanes_reservable ? max(0, $this->lanes - (int) $this->event_lanes) : 0;
    }

    #[Scope]
    protected function between(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $until);
    }

    /**
     * Every log entry names the day and part of the day, also when only the lanes changed or the slot was closed.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('slot', [
            'date' => $this->date?->toDateString(),
            'period' => $this->period?->value,
            'times' => $this->starts_at && $this->ends_at ? $this->times() : null,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('schedule')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
