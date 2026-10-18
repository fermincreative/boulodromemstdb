<?php

namespace App\Actions\Mail;

use App\Jobs\SendMemberMail;
use App\Models\Member;
use InvalidArgumentException;

/**
 * Queues one of the editable e-mails for a member, as written (or edited) in the panel.
 */
class QueueMemberMail
{
    public function handle(Member $member, string $templateKey, string $subject, string $body): void
    {
        if (blank($member->email)) {
            throw new InvalidArgumentException('Dit lid heeft geen e-mailadres.');
        }

        SendMemberMail::dispatch($member, $templateKey, $subject, $body, auth()->id());
    }
}
