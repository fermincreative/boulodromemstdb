<?php

namespace App\Services;

use App\Models\JoiningRate;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * What a new member pays and for which periods.
 */
final readonly class NewMemberQuote
{
    /**
     * @param  array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, amount_cents: int}>  $periods
     */
    public function __construct(
        public JoiningRate $rate,
        public array $periods,
        public string $description,
    ) {}

    public function totalCents(): int
    {
        return array_sum(array_column($this->periods, 'amount_cents'));
    }

    /**
     * "€ 67,50 (instap vanaf 1 april)" or "€ 10,00 voor 2026 + € 90,00 voor 2027 = € 100,00".
     */
    public function summary(): string
    {
        if (count($this->periods) === 1) {
            return Money::format($this->totalCents())." (instap vanaf {$this->rate->startsOnLabel()})";
        }

        return collect($this->periods)
            ->map(fn (array $period) => Money::format($period['amount_cents']).' voor '.$period['starts_at']->year)
            ->implode(' + ').' = '.Money::format($this->totalCents());
    }
}
