<?php

namespace App\Services;

use App\Enums\SchedulePeriod;
use App\Models\Setting;
use InvalidArgumentException;

/**
 * Standard times of Middag and Avond (Beheer → Openingstijden), and checks on times ("HH:MM").
 */
class OpeningHours
{
    /** @var array<string, array{0: string, 1: string}> */
    private const DEFAULTS = [
        'afternoon' => ['13:00', '17:00'],
        'evening' => ['19:00', '23:00'],
    ];

    /**
     * @return array{0: string, 1: string} Start and end, e.g. ["13:00", "17:00"].
     */
    public static function times(SchedulePeriod $period): array
    {
        if (! $period->isStandard()) {
            throw new InvalidArgumentException('Een extra dagdeel heeft geen standaardtijden.');
        }

        $times = Setting::get(self::settingKey($period));

        return is_array($times) && count($times) === 2 ? [$times[0], $times[1]] : self::DEFAULTS[$period->value];
    }

    public static function settingKey(SchedulePeriod $period): string
    {
        return "schedule_{$period->value}_times";
    }

    /**
     * A time of day as typed, in 24-hour notation, to "HH:MM": "13:00", "13:00:00", "9:30", "9.30", "1330", "930", "9" → "09:00".
     * Null when it is not a time of day.
     */
    public static function normalize(?string $time): ?string
    {
        $time = trim((string) $time);

        if (preg_match('/^(\d{1,2})[:.](\d{2})(:\d{2})?$/', $time, $m) || preg_match('/^(\d{1,2})(\d{2})$/', $time, $m)) {
            [$hours, $minutes] = [(int) $m[1], (int) $m[2]];
        } elseif (preg_match('/^\d{1,2}$/', $time)) {
            [$hours, $minutes] = [(int) $time, 0];
        } else {
            return null;
        }

        return $hours <= 23 && $minutes <= 59 ? sprintf('%02d:%02d', $hours, $minutes) : null;
    }

    /**
     * "13:00–17:00"
     */
    public static function format(string $startsAt, string $endsAt): string
    {
        return "{$startsAt}–{$endsAt}";
    }
}
