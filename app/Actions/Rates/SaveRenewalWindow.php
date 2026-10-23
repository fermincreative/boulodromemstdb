<?php

namespace App\Actions\Rates;

use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * How many days before a membership ends the member shows up under "Te verlengen".
 */
class SaveRenewalWindow
{
    public function handle(int $days): void
    {
        if ($days < 1 || $days > 365) {
            throw ValidationException::withMessages(['renewal_window_days' => 'Kies een aantal dagen tussen 1 en 365.']);
        }

        Setting::put(Setting::RENEWAL_WINDOW_DAYS, $days);
    }
}
