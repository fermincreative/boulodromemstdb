<?php

namespace App\Mail;

use App\Actions\Members\UpdateContactDetails;
use App\Models\Member;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a member's new e-mail address: the change only happens after clicking the link in it.
 */
class ConfirmEmailChangeMail extends Mailable
{
    public function __construct(public Member $member, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('portal.email_change.subject', ['association' => config('membership_card.association_name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: [
            'body' => __('portal.email_change.body', [
                'name' => $this->member->first_name,
                'association' => config('membership_card.association_name'),
                'url' => $this->url,
                'hours' => UpdateContactDetails::EMAIL_CONFIRMATION_HOURS,
            ]),
        ]);
    }
}
