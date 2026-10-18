<?php

namespace App\Services;

use App\Models\JoiningRate;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Membership fees. Periods follow the calendar year.
 *
 * - Renewal: the annual fee for the whole year.
 * - New member: the joining rate for the date they sign up, for the rest of this year. A rate with
 *   "includes next year" (December) also adds next year's annual fee, as one payment.
 */
class Fees
{
    public function annualFeeCents(): int
    {
        return (int) Setting::get(Setting::ANNUAL_FEE_CENTS, 0);
    }

    public function joiningRateOn(CarbonImmutable $date): JoiningRate
    {
        $rate = JoiningRate::query()
            ->where('starts_on', '<=', $date->format('m-d'))
            ->orderByDesc('starts_on')
            ->first();

        if (! $rate) {
            throw new RuntimeException('Er is geen instaptarief ingesteld voor '.$date->format('d-m').'.');
        }

        return $rate;
    }

    public function quoteForNewMember(?CarbonImmutable $signUpDate = null): NewMemberQuote
    {
        $date = ($signUpDate ?? CarbonImmutable::today())->startOfDay();
        $rate = $this->joiningRateOn($date);

        $periods = [[
            'starts_at' => $date,
            'ends_at' => $date->endOfYear()->startOfDay(),
            'amount_cents' => $rate->amount_cents,
        ]];

        if ($rate->includes_next_year) {
            $nextYear = $date->addYear()->startOfYear();
            $periods[] = [
                'starts_at' => $nextYear,
                'ends_at' => $nextYear->endOfYear()->startOfDay(),
                'amount_cents' => $this->annualFeeCents(),
            ];
        }

        $description = "Contributie {$date->year} (instap vanaf {$rate->startsOnLabel()})"
            .($rate->includes_next_year ? ' en '.($date->year + 1) : '');

        return new NewMemberQuote($rate, $periods, $description);
    }
}
