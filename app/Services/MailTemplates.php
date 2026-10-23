<?php

namespace App\Services;

use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Markdown;
use App\Support\Money;
use App\Support\PaymentLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fills in the {placeholders} of the editable e-mail texts.
 */
class MailTemplates
{
    /**
     * Placeholders whose values members type themselves (sign-up form): escaped in the Markdown body.
     */
    private const MEMBER_TYPED = ['{voornaam}', '{naam}'];

    /**
     * Placeholders available in every e-mail.
     *
     * @var array<string, string>
     */
    private const COMMON = [
        '{voornaam}' => 'Voornaam van het lid',
        '{naam}' => 'Volledige naam van het lid',
        '{lidnummer}' => 'Lidnummer (bijv. SBM-0261-91)',
        '{vereniging}' => 'Naam van de vereniging',
        '{iban}' => 'Rekeningnummer van de vereniging',
        '{rekeninghouder}' => 'Ten name van (rekeninghouder)',
    ];

    /**
     * Extra placeholders per e-mail.
     *
     * @var array<string, array<string, string>>
     */
    private const EXTRA = [
        MailTemplate::MEMBERSHIP_CARD => [
            '{jaar}' => 'Jaar van de kaart',
            '{geldig_tot}' => 'Einddatum van het lidmaatschap',
        ],
        MailTemplate::WELCOME => [
            '{bedrag}' => 'Te betalen bedrag (bijv. € 22,50)',
            '{omschrijving}' => 'Omschrijving van de betaling',
            '{betaalkenmerk}' => 'Wat het lid bij de overboeking vermeldt',
            '{betaallink}' => 'Link om online te betalen (iDEAL, Bancontact)',
            '{geldig_tot}' => 'Einddatum van het lidmaatschap',
        ],
        MailTemplate::RENEWAL => [
            '{jaar}' => 'Jaar van de verlenging',
            '{bedrag}' => 'Te betalen bedrag (bijv. € 90,00)',
            '{omschrijving}' => 'Omschrijving van de betaling',
            '{betaalkenmerk}' => 'Wat het lid bij de overboeking vermeldt',
            '{betaallink}' => 'Link om online te betalen (iDEAL, Bancontact)',
            '{geldig_tot}' => 'Einddatum van het nieuwe lidmaatschap',
        ],
        MailTemplate::PAYMENT_CONFIRMATION => [
            '{bedrag}' => 'Betaald bedrag',
            '{omschrijving}' => 'Omschrijving van de betaling',
            '{betaald_op}' => 'Datum waarop de betaling is verwerkt',
            '{betaalwijze}' => 'Contant / overboeking / iDEAL / Bancontact',
            '{geldig_tot}' => 'Einddatum van het lidmaatschap',
        ],
        MailTemplate::CANCELLATION => [
            '{opgezegd_per}' => 'Datum waarop het lidmaatschap eindigt',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function placeholders(?string $key = null): array
    {
        return self::COMMON + (self::EXTRA[$key] ?? []);
    }

    /**
     * @param  array<string, string>  $values
     * @return array{subject: string, body: string}
     */
    public function render(string $key, array $values, ?string $subject = null, ?string $body = null): array
    {
        $template = ($subject === null || $body === null) ? MailTemplate::forKey($key) : null;

        return [
            'subject' => strtr($subject ?? $template->subject, $values),
            // The body is Markdown: values members typed themselves must not become links or formatting.
            'body' => strtr($body ?? $template->body, array_merge($values, array_map(
                fn (string $value) => Markdown::escape($value),
                array_intersect_key($values, array_flip(self::MEMBER_TYPED)),
            ))),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderForMembership(string $key, Membership $membership): array
    {
        return $this->render($key, $this->memberValues($membership->member) + [
            '{jaar}' => (string) $membership->cardYear(),
            '{geldig_tot}' => $membership->ends_at->format('d-m-Y'),
        ]);
    }

    /**
     * Aanmelding: welcome with the payment details of an (open) payment.
     *
     * @return array{subject: string, body: string}
     */
    public function renderWelcome(Payment $payment): array
    {
        return $this->render(MailTemplate::WELCOME, $this->memberValues($payment->member) + $this->paymentValues($payment));
    }

    /**
     * Verlenging: the payment request for next year, with the online payment link. A subject and body with {placeholders}
     * (edited for one bulk send) replace the saved text.
     *
     * @return array{subject: string, body: string}
     */
    public function renderRenewal(Payment $payment, ?string $subject = null, ?string $body = null): array
    {
        $startsAt = $payment->memberships()->min('starts_at');

        return $this->render(MailTemplate::RENEWAL, $this->memberValues($payment->member) + $this->paymentValues($payment) + [
            '{jaar}' => $startsAt ? substr($startsAt, 0, 4) : (string) (today()->year + 1),
        ], $subject, $body);
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderPaymentConfirmation(Payment $payment): array
    {
        return $this->render(MailTemplate::PAYMENT_CONFIRMATION, $this->memberValues($payment->member) + $this->paymentValues($payment));
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderCancellation(Member $member, CarbonInterface $cancelledAt): array
    {
        return $this->render(MailTemplate::CANCELLATION, $this->memberValues($member) + [
            '{opgezegd_per}' => $cancelledAt->format('d-m-Y'),
        ]);
    }

    /**
     * Example values, for the preview on the E-mailteksten page.
     *
     * @return array{subject: string, body: string}
     */
    public function renderExample(string $key): array
    {
        return $this->render($key, [
            '{voornaam}' => 'Marcella',
            '{naam}' => 'Marcella Lammerts van Beuren',
            '{lidnummer}' => 'SBM-0261-91',
            '{vereniging}' => config('membership_card.association_name'),
            '{iban}' => (string) Setting::get(Setting::BANK_IBAN),
            '{rekeninghouder}' => (string) Setting::get(Setting::BANK_ACCOUNT_HOLDER),
            '{jaar}' => (string) today()->year,
            '{geldig_tot}' => today()->endOfYear()->format('d-m-Y'),
            '{bedrag}' => Money::format(2250),
            '{omschrijving}' => 'Contributie '.today()->year.' (instap vanaf 1 oktober)',
            '{betaalkenmerk}' => 'Contributie '.today()->year.' SBM-0261-91',
            '{betaallink}' => url('/betalen/261?signature=voorbeeld'),
            '{betaald_op}' => today()->format('d-m-Y'),
            '{betaalwijze}' => 'overboeking',
            '{opgezegd_per}' => today()->format('d-m-Y'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function memberValues(Member $member): array
    {
        return [
            '{voornaam}' => $member->first_name,
            '{naam}' => $member->full_name,
            '{lidnummer}' => (string) $member->display_number,
            '{vereniging}' => config('membership_card.association_name'),
            '{iban}' => (string) Setting::get(Setting::BANK_IBAN),
            '{rekeninghouder}' => (string) Setting::get(Setting::BANK_ACCOUNT_HOLDER),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentValues(Payment $payment): array
    {
        $endsAt = $payment->memberships()->max('ends_at');
        $firstYear = $payment->memberships()->min('starts_at');

        return [
            '{bedrag}' => Money::format($payment->amount_cents),
            '{omschrijving}' => $payment->description,
            '{betaalkenmerk}' => 'Contributie '.($firstYear ? substr($firstYear, 0, 4) : today()->year).' '.$payment->member->display_number,
            '{betaald_op}' => $payment->paid_at?->format('d-m-Y') ?? '',
            '{betaalwijze}' => $payment->method?->isOnline() ? $payment->method->getLabel() : mb_strtolower((string) $payment->method?->getLabel()),
            '{betaallink}' => PaymentLink::for($payment),
            '{geldig_tot}' => $endsAt ? Carbon::parse($endsAt)->format('d-m-Y') : '',
        ];
    }
}
