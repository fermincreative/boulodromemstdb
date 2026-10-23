<?php

namespace App\Mail;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\Markdown;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the ledenadministratie about a sign-up through the website. Only name and number; details are in the panel.
 */
class NewSignUpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Member $member) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nieuwe aanmelding: {$this->member->full_name} ({$this->member->display_number})");
    }

    public function content(): Content
    {
        $payment = $this->member->payments()->latest('id')->first();

        return new Content(markdown: 'mail.template', with: [
            'body' => "Er is een nieuwe aanmelding binnengekomen via de website.\n\n"
                .'- Naam: '.Markdown::escape($this->member->full_name)."\n"
                ."- Lidnummer: {$this->member->display_number}\n"
                .($payment ? '- Contributie: '.Money::format($payment->amount_cents)." (openstaand)\n" : '')
                ."\nBekijk het lid in de ledenadministratie: ".MemberResource::getUrl('view', ['record' => $this->member], panel: 'admin'),
        ]);
    }
}
