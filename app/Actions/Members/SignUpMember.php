<?php

namespace App\Actions\Members;

use App\Jobs\SendMemberMail;
use App\Mail\NewSignUpMail;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Payment;
use App\Services\MailTemplates;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

/**
 * A sign-up through the public form: member + first membership + open payment (as in the panel),
 * then the "Aanmelding" e-mail with payment details to the member and a notice to the ledenadministratie.
 */
class SignUpMember
{
    public function __construct(private CreateMember $createMember, private MailTemplates $templates) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Member
    {
        $member = $this->createWithFreshNumber($attributes);
        $payment = $member->payments()->latest('id')->firstOrFail();

        activity('members')
            ->performedOn($member)
            ->event('signed_up')
            ->log('Aangemeld via de website');

        $mail = $this->templates->renderWelcome($payment);
        SendMemberMail::dispatch($member, MailTemplate::WELCOME, $mail['subject'], $mail['body']);

        if ($notify = config('members.signup_notify_address') ?: config('mail.reply_to.address')) {
            Mail::to($notify)->queue(new NewSignUpMail($member));
        }

        return $member;
    }

    /**
     * Two sign-ups at the same moment could pick the same next member number; the second one simply retries.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createWithFreshNumber(array $attributes, int $attempts = 3): Member
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createMember->handle($attributes, withMembership: true);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    public static function paymentFor(Member $member): ?Payment
    {
        return $member->payments()->latest('id')->first();
    }
}
