<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Reads dates as Dutch people type them: day first, any separator, short or long year.
 *
 *   10-09-1992, 10/9/1992, 10.9.1992, 10 9 1992, 10-9-92, 10091992, 1992-09-10 (ISO)
 */
final class DutchDate
{
    public static function parse(?string $input): ?CarbonImmutable
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        // ISO (as stored, and as the date picker returns it): 1992-09-10
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?: 00:00(?::00)?)?$/', $input, $m)) {
            return self::make((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Day first with a separator: 10-09-1992, 10/9/92, 10.9.1992, 10 9 1992
        if (preg_match('/^(\d{1,2})\s*[-\/.\s]\s*(\d{1,2})\s*[-\/.\s]\s*(\d{2}|\d{4})$/', $input, $m)) {
            return self::make(self::fullYear($m[3]), (int) $m[2], (int) $m[1]);
        }

        // Without separators: 10091992 or 100992
        if (preg_match('/^(\d{2})(\d{2})(\d{2}|\d{4})$/', $input, $m)) {
            return self::make(self::fullYear($m[3]), (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    public static function format(?CarbonImmutable $date): ?string
    {
        return $date?->format('d-m-Y');
    }

    /**
     * Two-digit years are read as the most recent matching year that is not in the future: 92 → 1992, 05 → 2005.
     */
    private static function fullYear(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        $century = intdiv(today()->year, 100) * 100;
        $candidate = $century + (int) $year;

        return $candidate > today()->year ? $candidate - 100 : $candidate;
    }

    private static function make(int $year, int $month, int $day): ?CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
