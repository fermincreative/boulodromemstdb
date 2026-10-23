<?php

namespace App\Actions\Schedule;

use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Models\ScheduleSlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What the Rooster form does when saved: set this part of the day, then, for an event, repeat it,
 * or for an occurrence of a repeated event, apply the same to the later occurrences. All or nothing.
 */
class EditScheduleSlot
{
    public function __construct(private SaveScheduleSlot $save, private ScheduleSeries $series) {}

    /**
     * @param  ScheduleSlot|null  $existing  The part of the day as it was before (null when it was closed / new).
     * @return array{slot: ScheduleSlot|null, repeated: int, changed: int, skipped: array<string, string>}
     */
    public function handle(
        CarbonInterface $date,
        SchedulePeriod $period,
        SlotSettings $settings,
        ?ScheduleSlot $existing = null,
        ?CarbonInterface $repeatUntil = null,
        int $repeatEveryWeeks = 1,
        bool $applyToFollowing = false,
    ): array {
        return DB::transaction(function () use ($date, $period, $settings, $existing, $repeatUntil, $repeatEveryWeeks, $applyToFollowing) {
            // The occurrence as it was: saving may take it out of its series (closed, or no longer an event).
            $before = $existing ? clone $existing : null;
            $slot = $this->save->apply($date, $period, $settings, $existing);
            $result = ['slot' => $slot, 'repeated' => 0, 'changed' => 0, 'skipped' => []];

            if ($before?->isRepeated() && $applyToFollowing) {
                ['changed' => $result['changed'], 'skipped' => $result['skipped']] = $this->series->applyToFollowing($before, $settings);
            } elseif ($repeatUntil && $slot && $settings->status === ScheduleSlotStatus::Event && ! $slot->isRepeated()) {
                ['created' => $result['repeated'], 'skipped' => $result['skipped']] = $this->series->repeat($slot, $repeatUntil, $repeatEveryWeeks);
            }

            return $result;
        });
    }
}
