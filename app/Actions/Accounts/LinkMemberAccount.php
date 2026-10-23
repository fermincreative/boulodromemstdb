<?php

namespace App\Actions\Accounts;

use App\Models\Member;
use App\Models\MemberAccount;
use Illuminate\Support\Facades\DB;

/**
 * Keeps members linked to the login account of their e-mail address. Accounts are created when needed
 * (first login request, sign-up), so existing members never have to be "invited" or given a password.
 */
class LinkMemberAccount
{
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /**
     * The account for this address, linking every current member that uses it. Null when no member has it
     * (or only former members without an account).
     */
    public function forEmail(string $email): ?MemberAccount
    {
        $email = self::normalize($email);

        if ($email === '') {
            return null;
        }

        $members = Member::current()->whereRaw('lower(trim(email)) = ?', [$email])->get();

        if ($members->isEmpty()) {
            // Former members can still log in to an existing account (they see that their membership has ended).
            return MemberAccount::where('email', $email)->has('members')->first();
        }

        return DB::transaction(function () use ($email, $members) {
            $account = MemberAccount::firstOrCreate(['email' => $email]);

            $members
                ->reject(fn (Member $member) => $member->member_account_id === $account->id)
                ->each(fn (Member $member) => $member->forceFill(['member_account_id' => $account->id])->saveQuietly());

            return $account;
        });
    }

    /**
     * Links a (new) member to the account of their e-mail address.
     */
    public function forMember(Member $member): ?MemberAccount
    {
        $account = filled($member->email) ? $this->forEmail($member->email) : null;

        // The link was saved through a fresh query; keep this instance in step without making it "dirty".
        $member->setAttribute('member_account_id', Member::whereKey($member->getKey())->value('member_account_id'));
        $member->syncOriginalAttribute('member_account_id');
        $member->unsetRelation('account');

        return $account;
    }

    /**
     * A member's e-mail address changed. If the member had the account to themselves, the account moves along
     * to the new address. Otherwise (a shared address, or the new address already has an account) the member
     * moves to the account of the new address. Without an address the member has no account.
     */
    public function emailChanged(Member $member): void
    {
        // From the database: this instance may have been loaded before the member was linked.
        $account = MemberAccount::find(Member::whereKey($member->getKey())->value('member_account_id'));
        $new = self::normalize($member->email);

        if ($account && $new !== '' && ! $account->members()->whereKeyNot($member->getKey())->exists()
            && ! MemberAccount::where('email', $new)->whereKeyNot($account->getKey())->exists()) {
            $account->update(['email' => $new]);
            // Links already sent went to the old address.
            $account->loginTokens()->whereNull('used_at')->delete();

            return;
        }

        if ($account) {
            $member->forceFill(['member_account_id' => null])->saveQuietly();
        }

        $this->forMember($member);
    }
}
