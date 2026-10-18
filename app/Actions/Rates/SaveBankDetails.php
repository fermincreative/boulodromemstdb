<?php

namespace App\Actions\Rates;

use App\Models\Setting;
use App\Support\Iban;
use Illuminate\Validation\ValidationException;

/**
 * The bank account members transfer their contribution to.
 */
class SaveBankDetails
{
    public function handle(string $iban, string $accountHolder): void
    {
        if (! Iban::isValid($iban)) {
            throw ValidationException::withMessages(['bank_iban' => 'Dit is geen geldig IBAN.']);
        }

        Setting::put(Setting::BANK_IBAN, Iban::format($iban));
        Setting::put(Setting::BANK_ACCOUNT_HOLDER, trim($accountHolder));
    }
}
