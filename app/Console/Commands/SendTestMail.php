<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {to : E-mailadres van de ontvanger}';

    protected $description = 'Verstuur direct (zonder wachtrij) een testmail om de mailinstellingen te controleren';

    public function handle(): int
    {
        $to = $this->argument('to');

        if (Validator::make(['to' => $to], ['to' => 'email'])->fails()) {
            $this->components->error("Ongeldig e-mailadres: {$to}");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $this->components->twoColumnDetail('Mailer', $mailer);
        $this->components->twoColumnDetail('Afzender', config('mail.from.name').' <'.config('mail.from.address').'>');
        $this->components->twoColumnDetail('Antwoorden naar', config('mail.reply_to.address'));

        if (in_array($mailer, ['ses', 'ses-v2'], true)) {
            $this->components->twoColumnDetail('SES-regio', config('services.ses.region'));
        }

        try {
            Mail::raw(
                'Dit is een testbericht van de ledenadministratie van '.config('membership_card.association_name').".\n\n".
                'Als u dit leest, werkt het versturen van e-mail.',
                fn (Message $message) => $message->to($to)->subject('Testbericht — '.config('membership_card.association_name')),
            );
        } catch (Throwable $e) {
            $this->components->error('Versturen mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($mailer === 'log'
            ? "Mailer is 'log': het bericht staat in storage/logs/laravel.log en is niet echt verstuurd."
            : "Testmail verstuurd naar {$to}.");

        return self::SUCCESS;
    }
}
