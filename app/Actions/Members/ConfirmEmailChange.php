<?php

namespace App\Actions\Members;

use App\Actions\Accounts\LinkMemberAccount;
use App\Mail\EmailChangedNoticeMail;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Spatie\Activitylog\Facades\CauserResolver;

/**
 * The member clicked the confirmation link in the mailbox of their new address: the address is changed.
 * The login follows automatically (LinkMemberAccount::emailChanged), and the old address is told about the change.
 */
class ConfirmEmailChange
{
    public function handle(Member $member, string $email): void
    {
        if (! self::applies($member, $email)) {
            throw new InvalidArgumentException('Deze bevestiging geldt niet meer.');
        }

        $old = $member->email;

        CauserResolver::setCauser($member->account);
        $member->update(['email' => LinkMemberAccount::normalize($email)]);

        // Tell the old address: if someone else made this change, the member finds out.
        if (filled($old)) {
            Mail::to($old)->send(new EmailChangedNoticeMail($member, $member->email));
        }
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
