<?php

namespace App\Mail;

use App\Models\Member;
use App\Support\Markdown;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the OLD address after a member's e-mail address was changed through the ledenportaal: if someone else
 * did it (e.g. on an unlocked phone), the member finds out and can contact the ledenadministratie.
 */
class EmailChangedNoticeMail extends Mailable
{
    public function __construct(public Member $member, public string $newEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('portal.email_changed_mail.subject', ['association' => config('membership_card.association_name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: [
            'body' => __('portal.email_changed_mail.body', [
                'name' => Markdown::escape($this->member->first_name),
                'association' => config('membership_card.association_name'),
                'email' => Markdown::escape($this->newEmail),
                'contact' => config('mail.reply_to.address') ?: config('mail.from.address'),
            ]),
        ]);
    }
}
