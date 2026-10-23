<?php

namespace App\Actions\Schedule;

use App\Enums\SchedulePeriod;
use App\Models\Setting;
use App\Services\OpeningHours;
use Illuminate\Validation\ValidationException;

/**
 * The standard times of Middag and Avond. Parts of a day already in the Rooster keep their times.
 */
class SaveOpeningHours
{
    public function handle(string $afternoonStart, string $afternoonEnd, string $eveningStart, string $eveningEnd): void
    {
        $times = [
            'afternoon' => [OpeningHours::normalize($afternoonStart), OpeningHours::normalize($afternoonEnd)],
            'evening' => [OpeningHours::normalize($eveningStart), OpeningHours::normalize($eveningEnd)],
        ];

        foreach ($times as $key => [$start, $end]) {
            if (! $start || ! $end) {
                throw ValidationException::withMessages(["{$key}_start" => 'Vul geldige tijden in (uu:mm).']);
            }

            if ($end <= $start) {
                throw ValidationException::withMessages(["{$key}_end" => 'De eindtijd moet na de begintijd liggen.']);
            }
        }

        if ($times['afternoon'][1] > $times['evening'][0]) {
            throw ValidationException::withMessages(['evening_start' => 'De avond moet beginnen na het einde van de middag.']);
        }

        Setting::put(OpeningHours::settingKey(SchedulePeriod::Afternoon), $times['afternoon']);
        Setting::put(OpeningHours::settingKey(SchedulePeriod::Evening), $times['evening']);
    }
}
