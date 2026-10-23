<?php

namespace App\Actions\Members;

use App\Actions\Accounts\LinkMemberAccount;
use App\Mail\ConfirmEmailChangeMail;
use App\Models\Member;
use App\Models\MemberAccount;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Facades\CauserResolver;

/**
 * A member changes their own contact details in the ledenportaal. Phone numbers and address are saved
 * right away; a new e-mail address only after it is confirmed from that mailbox (it is also the login).
 */
class UpdateContactDetails
{
    /** Fields a member may change themselves. */
    public const FIELDS = ['phone_mobile', 'phone_home', 'street', 'postal_code', 'city', 'country'];

    /** How long the confirmation link for a new e-mail address works. */
    public const EMAIL_CONFIRMATION_HOURS = 48;

    /**
     * @param  array<string, mixed>  $data
     * @return string|null The new e-mail address a confirmation was sent to, if the address was changed.
     */
    public function handle(Member $member, array $data, MemberAccount $by): ?string
    {
        // The automatic "Lidgegevens gewijzigd" log entry names the member as the one who changed it.
        CauserResolver::setCauser($by);

        $member->update(collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => filled($data[$field] ?? null) ? trim((string) $data[$field]) : null])
            ->all());

        $email = LinkMemberAccount::normalize($data['email'] ?? '');

        if ($email === '' || $email === LinkMemberAccount::normalize($member->email)) {
            return null;
        }

        $url = URL::temporarySignedRoute('portal.email.confirm', now()->addHours(self::EMAIL_CONFIRMATION_HOURS), [
            'member' => $member->getKey(),
            'email' => $email,
        ]);

        Mail::to($email)->send(new ConfirmEmailChangeMail($member, $url));

        activity('members')
            ->performedOn($member)
            ->causedBy($by)
            ->event('email_change_requested')
            ->withProperties(['email' => $email])
            ->log('Nieuw e-mailadres aangevraagd via het ledenportaal (wacht op bevestiging)');

        return $email;
    }
}
