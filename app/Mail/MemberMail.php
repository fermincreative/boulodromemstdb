<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An e-mail to a member with a subject and Markdown text from one of the editable e-mail texts.
 */
class MemberMail extends Mailable
{
    public function __construct(public string $mailSubject, public string $mailBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: ['body' => $this->mailBody]);
    }
}
