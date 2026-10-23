<?php

namespace App\Actions\Members;

use App\Actions\Accounts\LinkMemberAccount;
use App\Models\Member;
use InvalidArgumentException;
use Spatie\Activitylog\Facades\CauserResolver;

/**
 * The member clicked the confirmation link in the mailbox of their new address: the address is changed.
 * The login follows automatically (LinkMemberAccount::emailChanged).
 */
class ConfirmEmailChange
{
    public function handle(Member $member, string $email): void
    {
        if (! self::applies($member, $email)) {
            throw new InvalidArgumentException('Deze bevestiging geldt niet meer.');
        }

        CauserResolver::setCauser($member->account);
        $member->update(['email' => LinkMemberAccount::normalize($email)]);
    }

    /**
     * Still to be done: the member is still a member and does not have this address yet.
     */
    public static function applies(Member $member, string $email): bool
    {
        return ! $member->hasLeft()
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && LinkMemberAccount::normalize($member->email) !== LinkMemberAccount::normalize($email);
    }
}
