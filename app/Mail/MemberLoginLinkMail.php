<?php

namespace App\Mail;

use App\Actions\Accounts\SendLoginLink;
use App\Models\MemberAccount;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The login link for the ledenportaal. Fixed text (lang/{locale}/portal.php), not editable: it is part of the security of the login.
 */
class MemberLoginLinkMail extends Mailable
{
    public function __construct(public MemberAccount $account, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('portal.login_mail.subject', ['association' => config('membership_card.association_name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: [
            'body' => __('portal.login_mail.body', [
                'name' => $this->account->greetingName(),
                'association' => config('membership_card.association_name'),
                'url' => $this->url,
                'minutes' => SendLoginLink::VALID_MINUTES,
            ]),
        ]);
    }
}
