<?php

use App\Jobs\SendMembershipCard;
use App\Models\Membership;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\artisan;

it('builds the SES transports from the SES settings', function (string $mailer, string $transport) {
    config(['services.ses.key' => 'AKIAEXAMPLE', 'services.ses.secret' => 'secret', 'services.ses.region' => 'eu-west-1']);

    expect(Mail::mailer($mailer)->getSymfonyTransport())->toBeInstanceOf($transport);
})->with([
    'ses' => ['ses', SesTransport::class],
    'ses-v2' => ['ses-v2', SesV2Transport::class],
]);

it('sets a reply-to address on every mail', function () {
    config(['mail.default' => 'array', 'mail.reply_to' => ['address' => 'leden@example.com', 'name' => 'Ledenadministratie']]);

    artisan('mail:test', ['to' => 'lid@example.com'])->assertSuccessful();

    $message = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($message->getReplyTo()[0]->getAddress())->toBe('leden@example.com')
        ->and($message->getTo()[0]->getAddress())->toBe('lid@example.com');
});

it('rejects an invalid test address', function () {
    artisan('mail:test', ['to' => 'geen-adres'])->assertFailed();
});

it('logs a card e-mail that failed for good', function () {
    $membership = Membership::factory()->create();

    (new SendMembershipCard($membership))->failed(new RuntimeException('Email address is not verified.'));

    $log = Activity::where('event', 'card_failed')->sole();
    expect($log->subject->is($membership))->toBeTrue()
        ->and($log->properties['error'])->toContain('not verified');
});
