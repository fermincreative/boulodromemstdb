<?php

namespace App\Actions\Accounts;

use App\Mail\MemberLoginLinkMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * E-mails a login link for the ledenportaal. Says nothing about whether the address is known:
 * the page shows the same message either way.
 */
class SendLoginLink
{
    /** How long a link works. */
    public const VALID_MINUTES = 30;

    public function __construct(private LinkMemberAccount $accounts) {}

    /**
     * @return bool Whether a link was sent (for tests and logging only; never shown to the visitor).
     */
    public function handle(string $email, ?string $ip = null): bool
    {
        $account = $this->accounts->forEmail($email);

        if (! $account) {
            return false;
        }

        $token = Str::random(64);

        $account->loginTokens()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
            'requested_ip' => $ip,
        ]);

        try {
            // Sent right away (not queued): the member is waiting for it.
            Mail::to($account->email)->send(new MemberLoginLinkMail($account, route('portal.confirm', ['token' => $token])));
        } catch (Throwable $e) {
            Log::error('Login link could not be sent', ['member_account_id' => $account->id, 'error' => $e->getMessage()]);

            return false;
        }

        activity('accounts')
            ->performedOn($account)
            ->event('login_link_sent')
            ->withProperties(['email' => $account->email])
            ->log('Inloglink verstuurd');

        return true;
    }
}
