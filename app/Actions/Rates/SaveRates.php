<?php

namespace App\Actions\Rates;

use App\Models\JoiningRate;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the annual fee and the joining rates. Changes apply to payments created from now on;
 * existing payments keep their amount.
 */
class SaveRates
{
    /**
     * @param  array<int, array{starts_on: string, amount_cents: int, includes_next_year: bool}>  $joiningRates
     */
    public function handle(int $annualFeeCents, array $joiningRates): void
    {
        $this->validate($annualFeeCents, $joiningRates);

        DB::transaction(function () use ($annualFeeCents, $joiningRates) {
            $old = [
                'annual_fee_cents' => (int) Setting::get(Setting::ANNUAL_FEE_CENTS),
                'joining_rates' => $this->snapshot(),
            ];

            Setting::put(Setting::ANNUAL_FEE_CENTS, $annualFeeCents);

            JoiningRate::query()->delete();
            foreach ($joiningRates as $rate) {
                JoiningRate::create($rate);
            }

            $new = ['annual_fee_cents' => $annualFeeCents, 'joining_rates' => $this->snapshot()];

            if ($old !== $new) {
                activity('settings')
                    ->event('rates_updated')
                    ->withProperties(['old' => $old, 'attributes' => $new])
                    ->log('Tarieven gewijzigd');
            }
        });
    }

    /**
     * @param  array<int, array{starts_on: string, amount_cents: int, includes_next_year: bool}>  $joiningRates
     */
    private function validate(int $annualFeeCents, array $joiningRates): void
    {
        $errors = [];
        $dates = array_column($joiningRates, 'starts_on');

        if ($annualFeeCents <= 0) {
            $errors['annual_fee'] = 'Vul een jaarcontributie in.';
        }

        if ($joiningRates === []) {
            $errors['joining_rates'] = 'Vul ten minste één instaptarief in.';
        } elseif (! in_array('01-01', $dates, true)) {
            $errors['joining_rates'] = 'Er moet een instaptarief zijn dat op 1 januari begint.';
        } elseif (count($dates) !== count(array_unique($dates))) {
            $errors['joining_rates'] = 'Twee instaptarieven beginnen op dezelfde datum.';
        }

        foreach ($dates as $date) {
            [$month, $day] = array_map('intval', explode('-', $date) + [1 => 0]);

            if (! checkdate($month, $day, 2024)) {
                $errors['joining_rates'] = "Ongeldige begindatum: {$day}-{$month}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<int, array{starts_on: string, amount_cents: int, includes_next_year: bool}>
     */
    private function snapshot(): array
    {
        return JoiningRate::orderBy('starts_on')->get()
            ->map(fn (JoiningRate $rate) => $rate->only(['starts_on', 'amount_cents', 'includes_next_year']))
            ->all();
    }
}
