<?php

namespace App\Jobs;

use App\Mail\MemberMail;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one of the editable e-mails (aanmelding, betalingsbevestiging, opzegging) and logs it on the member.
 */
class SendMemberMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public function __construct(
        public Member $member,
        public string $templateKey,
        public string $subject,
        public string $body,
        public ?int $sentById = null,
    ) {}

    public function handle(): void
    {
        if (blank($this->member->email)) {
            return;
        }

        Mail::to($this->member->email, $this->member->full_name)->send(new MemberMail($this->subject, $this->body));

        activity('members')
            ->performedOn($this->member)
            ->causedBy($this->sender())
            ->event('mail_sent')
            ->withProperties(['template' => $this->templateKey, 'email' => $this->member->email, 'subject' => $this->subject])
            ->log('E-mail verstuurd: '.$this->templateName());
    }

    public function failed(?Throwable $exception): void
    {
        activity('members')
            ->performedOn($this->member)
            ->causedBy($this->sender())
            ->event('mail_failed')
            ->withProperties(['template' => $this->templateKey, 'error' => $exception ? Str::limit($exception->getMessage(), 500) : null])
            ->log('E-mail mislukt: '.$this->templateName());
    }

    private function templateName(): string
    {
        return MailTemplate::where('key', $this->templateKey)->value('name') ?? $this->templateKey;
    }

    private function sender(): ?User
    {
        return $this->sentById ? User::find($this->sentById) : null;
    }
}
