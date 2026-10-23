<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Parts of a day in the Rooster: the two standard ones (times under Beheer → Openingstijden)
 * and extra ones an admin adds on a day with their own times.
 */
enum SchedulePeriod: string implements HasLabel
{
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Afternoon => 'Middag',
            self::Evening => 'Avond',
            self::Custom => 'Extra dagdeel',
        };
    }

    public function isStandard(): bool
    {
        return $this !== self::Custom;
    }

    /**
     * @return array<int, self>
     */
    public static function standard(): array
    {
        return [self::Afternoon, self::Evening];
    }
}
