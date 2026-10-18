<?php

namespace App\Mail;

use App\Models\MailTemplate;
use App\Models\Membership;
use App\Services\MailTemplates;
use App\Services\MembershipCard;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MembershipCardMail extends Mailable
{
    public string $mailSubject;

    public string $mailBody;

    /**
     * Without subject/body the current "Lidmaatschapskaart" template is used.
     */
    public function __construct(public Membership $membership, ?string $subject = null, ?string $body = null)
    {
        if ($subject === null || $body === null) {
            $default = app(MailTemplates::class)->renderForMembership(MailTemplate::MEMBERSHIP_CARD, $membership);
        }

        $this->mailSubject = $subject ?? $default['subject'];
        $this->mailBody = $body ?? $default['body'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.template',
            with: ['body' => $this->mailBody],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $card = app(MembershipCard::class);

        return collect([MembershipCard::PHONE, MembershipCard::PRINT])
            ->map(fn (string $variant) => Attachment::fromData(
                fn () => $card->pdf($this->membership, $variant),
                $card->filename($this->membership, $variant),
            )->withMime('application/pdf'))
            ->all();
    }
}
