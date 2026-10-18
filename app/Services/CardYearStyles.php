<?php

namespace App\Services;

use App\Models\CardYearStyle;

/**
 * Badge colours for a card year: the year's own style if an admin set one, otherwise the default accent.
 */
class CardYearStyles
{
    /**
     * Below this WCAG contrast the year is hard to read on the badge.
     */
    public const MIN_CONTRAST = 3.0;

    /**
     * @return array{badge: string, text: string}
     */
    public function for(int $year): array
    {
        $style = CardYearStyle::firstWhere('year', $year);

        return [
            'badge' => strtoupper($style?->badge_color ?? config('membership_card.colors.accent')),
            'text' => strtoupper($style?->text_color ?? '#000000'),
        ];
    }
}
