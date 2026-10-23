<?php

namespace App\Actions\Schedule;

use App\Enums\ScheduleSlotStatus;

/**
 * What an admin chose for a part of a day, so it can be applied to several dates (repeated events).
 */
final readonly class SlotSettings
{
    public function __construct(
        public ScheduleSlotStatus $status,
        public ?int $lanes = null,
        public ?string $eventName = null,
        public bool $eventNamePublic = false,
        public ?int $eventLanes = null,
        public bool $remainingLanesReservable = false,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
    ) {}
}
