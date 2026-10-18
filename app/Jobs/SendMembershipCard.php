<?php

namespace App\Jobs;

use App\Actions\Cards\QueueMembershipCard;
use App\Mail\MembershipCardMail;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates both card PDFs, e-mails them and marks the card as sent once the mail went out.
 */
class SendMembershipCard implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * Subject and body as edited in the send dialog; null = the current template.
     */
    public function __construct(
        public Membership $membership,
        public ?string $subject = null,
        public ?string $body = null,
        public ?int $sentById = null,
    ) {}

    public function handle(): void
    {
        $membership = $this->membership->load('member.club');
        $member = $membership->member;

        // Re-check at send time: things may have changed since the job was queued.
        if (app(QueueMembershipCard::class)->cannotSendReason($membership) !== null) {
            return;
        }

        $mail = new MembershipCardMail($membership, $this->subject, $this->body);

        Mail::to($member->email, $member->full_name)->send($mail);

        $membership->forceFill(['card_sent_at' => now()])->saveQuietly();

        activity('memberships')
            ->performedOn($membership)
            ->causedBy($this->sender())
            ->event('card_sent')
            ->withProperties(['email' => $member->email, 'year' => $membership->cardYear(), 'subject' => $mail->mailSubject])
            ->log('Lidmaatschapskaart verstuurd');
    }

    /**
     * All attempts failed (e.g. SES rejected the address): leave a trace admins can see.
     */
    public function failed(?Throwable $exception): void
    {
        activity('memberships')
            ->performedOn($this->membership)
            ->causedBy($this->sender())
            ->event('card_failed')
            ->withProperties(['error' => $exception ? Str::limit($exception->getMessage(), 500) : null])
            ->log('Versturen lidmaatschapskaart mislukt');
    }

    /**
     * The panel user who clicked "Kaart versturen"; the queue worker itself has no logged-in user.
     */
    private function sender(): ?User
    {
        return $this->sentById ? User::find($this->sentById) : null;
    }
}
