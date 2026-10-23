<?php

namespace App\Actions\Accounts;

use App\Models\MemberAccount;
use App\Models\MemberLoginToken;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Uses a login link: valid once, within its time. Returns the account to log in.
 */
class LogInWithLink
{
    public function handle(string $token): MemberAccount
    {
        return DB::transaction(function () use ($token) {
            $login = MemberLoginToken::where('token_hash', hash('sha256', $token))->first();

            if (! $login?->isUsable()) {
                throw new InvalidArgumentException(__('portal.confirm.expired_error'));
            }

            $login->update(['used_at' => now()]);
            $account = $login->account;
            $account->forceFill(['last_login_at' => now()])->save();

            activity('accounts')
                ->performedOn($account)
                ->causedBy($account)
                ->event('logged_in')
                ->log('Ingelogd op het ledenportaal');

            return $account;
        });
    }

    /**
     * Whether a link can still be used, without using it (for the confirmation page).
     */
    public static function isUsable(string $token): bool
    {
        return (bool) MemberLoginToken::where('token_hash', hash('sha256', $token))->first()?->isUsable();
    }
}
