<?php

use App\Actions\Members\CreateMember as CreateMemberAction;
use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Rates;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Jobs\SendMemberMail;
use App\Jobs\SendMembershipCard;
use App\Mail\MemberMail;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailTemplates;
use App\Support\Iban;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 18));
    $this->admin = User::factory()->admin()->create();
    actingAs($this->admin);
});

function memberWithOpenPayment(array $attributes = []): Member
{
    return app(CreateMemberAction::class)->handle(
        ['first_name' => 'Mees', 'last_name' => 'Jonkman', 'email' => 'mees@example.nl', 'member_number' => 1, ...$attributes],
        withMembership: true,
    );
}

describe('aanmelding', function () {
    it('fills in the payment details', function () {
        $member = memberWithOpenPayment();

        $mail = app(MailTemplates::class)->renderWelcome($member->payments()->sole());

        expect($mail['subject'])->toBe('Welkom bij Boulodrôme Maastricht — uw aanmelding')
            ->and($mail['body'])->toContain('Beste Mees Jonkman')
            ->toContain('**€ 22,50** (Contributie 2026 (instap vanaf 1 oktober))')
            ->toContain('Rekeningnummer: **NL96 ABNA 0617 0464 84**')
            ->toContain('Ten name van: Stichting Boulodrôme Maastricht')
            ->toContain('Onder vermelding van: Contributie 2026 SBM-0001-95')
            ->toContain('tot en met 31-12-2026')
            ->not->toContain('{');
    });

    it('opens the editable welcome e-mail right after creating a member with an open payment', function () {
        $component = Livewire::test(CreateMember::class)
            ->fillForm(['first_name' => 'Mees', 'last_name' => 'Jonkman', 'email' => 'mees@example.nl'])
            ->call('create');

        $member = Member::sole();
        $component->assertRedirect(MemberResource::getUrl('view', ['record' => $member, 'welcome' => 1]));

        // The browser opens the default action on load; here we check it is set and then open it.
        Livewire::withQueryParams(['welcome' => 1])
            ->test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertSet('defaultAction', 'sendWelcome')
            ->mountAction('sendWelcome')
            ->assertSchemaStateSet(['subject' => 'Welkom bij Boulodrôme Maastricht — uw aanmelding'])
            ->setActionData(['subject' => 'Welkom, Mees!', 'body' => "Beste Mees,\n\nAangepaste tekst."])
            ->callMountedAction();

        Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::WELCOME
            && $job->subject === 'Welkom, Mees!' && str_contains($job->body, 'Aangepaste tekst.'));
    });

    it('does not offer the welcome e-mail without e-mail address or when already paid', function () {
        Livewire::test(CreateMember::class)
            ->fillForm(['first_name' => 'Zonder', 'last_name' => 'Mail'])
            ->call('create')
            ->assertRedirect(MemberResource::getUrl('view', ['record' => Member::firstWhere('first_name', 'Zonder')]));

        Livewire::test(CreateMember::class)
            ->fillForm(['first_name' => 'Al', 'last_name' => 'Betaald', 'email' => 'al@example.nl', 'payment' => 'cash'])
            ->call('create')
            ->assertRedirect(MemberResource::getUrl('view', ['record' => Member::firstWhere('first_name', 'Al')]));
    });

    it('can be sent again later from the member page', function () {
        $member = memberWithOpenPayment();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('sendWelcome')
            ->assertHasNoActionErrors();

        Queue::assertPushed(SendMemberMail::class);
    });
});

describe('betalingsbevestiging', function () {
    it('is sent by default when marking paid, with the date it was processed', function () {
        $payment = memberWithOpenPayment()->payments()->sole();

        Livewire::test(ListPayments::class)
            ->callTableAction('markPaid', $payment, ['method' => 'bank_transfer', 'paid_at' => '16-10-2026', 'send_card' => false, 'send_confirmation' => true]);

        Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::PAYMENT_CONFIRMATION
            && str_contains($job->body, 'ontvangen en op 16-10-2026 verwerkt')
            && str_contains($job->body, '**€ 22,50**'));
    });

    it('is not editable in the dialog, only switched on or off', function () {
        $payment = memberWithOpenPayment()->payments()->sole();

        Livewire::test(ListPayments::class)
            ->mountTableAction('markPaid', $payment)
            ->assertTableActionDataSet(['send_confirmation' => true])
            ->assertFormFieldDoesNotExist('body');
    });

    it('is not sent when switched off', function () {
        $payment = memberWithOpenPayment()->payments()->sole();

        app(MarkPaymentPaid::class)->handle($payment, PaymentMethod::Cash, sendConfirmation: false);

        Queue::assertNotPushed(SendMemberMail::class);
    });

    it('goes out with card when a new member pays on the spot', function () {
        Livewire::test(CreateMember::class)
            ->fillForm(['first_name' => 'Al', 'last_name' => 'Betaald', 'email' => 'al@example.nl', 'payment' => 'cash'])
            ->call('create');

        Queue::assertPushed(SendMemberMail::class, fn ($job) => $job->templateKey === MailTemplate::PAYMENT_CONFIRMATION);
        Queue::assertPushed(SendMembershipCard::class);
    });
});

describe('opzegging', function () {
    it('lets the cancellation e-mail be edited and follows the chosen date', function () {
        $member = memberWithOpenPayment();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->mountAction('cancel')
            ->assertSchemaStateSet(['notify' => true, 'subject' => 'Bevestiging opzegging lidmaatschap — Boulodrôme Maastricht'])
            ->setActionData(['cancelled_at' => '31-12-2026'])
            ->assertActionDataSet(fn (array $data): bool => str_contains($data['body'], 'opgezegd per 31-12-2026'))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($member->fresh()->cancelled_at->toDateString())->toBe('2026-12-31');
        Queue::assertPushed(SendMemberMail::class, fn ($job) => $job->templateKey === MailTemplate::CANCELLATION
            && str_contains($job->body, 'opgezegd per 31-12-2026'));
    });

    it('keeps a hand-edited text when the date changes', function () {
        $member = memberWithOpenPayment();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->mountAction('cancel')
            ->setActionData(['body' => 'Eigen tekst'])
            ->setActionData(['cancelled_at' => '31-12-2026'])
            ->callMountedAction();

        Queue::assertPushed(SendMemberMail::class, fn ($job) => $job->body === 'Eigen tekst');
    });

    it('can cancel without informing the member', function () {
        $member = memberWithOpenPayment();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('cancel', ['cancelled_at' => '18-10-2026', 'notify' => false]);

        expect($member->fresh()->isCancelled())->toBeTrue();
        Queue::assertNotPushed(SendMemberMail::class);
    });
});

it('sends the member e-mail, even to a cancelled member, and logs it', function () {
    Mail::fake();
    $member = memberWithOpenPayment(['cancelled_at' => '2026-10-18']);

    (new SendMemberMail($member, MailTemplate::CANCELLATION, 'Onderwerp', "**Vet**\n\n<script>x</script>", $this->admin->id))->handle();

    Mail::assertSent(MemberMail::class, fn (MemberMail $mail) => $mail->hasTo('mees@example.nl'));
    $log = Activity::where('event', 'mail_sent')->sole();
    expect($log->description)->toBe('E-mail verstuurd: Opzegging')
        ->and($log->causer->is($this->admin))->toBeTrue();
});

it('lists the right placeholders per e-mail text', function () {
    expect(MailTemplates::placeholders(MailTemplate::WELCOME))->toHaveKeys(['{iban}', '{bedrag}', '{betaalkenmerk}'])
        ->and(MailTemplates::placeholders(MailTemplate::CANCELLATION))->toHaveKey('{opgezegd_per}')
        ->not->toHaveKey('{bedrag}');
});

it('renders an example of every e-mail text without leftover placeholders', function (string $key) {
    $mail = app(MailTemplates::class)->renderExample($key);

    expect($mail['subject'].$mail['body'])->not->toContain('{');
})->with([MailTemplate::MEMBERSHIP_CARD, MailTemplate::WELCOME, MailTemplate::PAYMENT_CONFIRMATION, MailTemplate::CANCELLATION]);

describe('bank details', function () {
    it('checks IBANs', function () {
        expect(Iban::isValid('NL96 ABNA 0617 0464 84'))->toBeTrue()
            ->and(Iban::isValid('NL96ABNA0617046485'))->toBeFalse()
            ->and(Iban::isValid('BE68 5390 0754 7034'))->toBeTrue()
            ->and(Iban::format('nl96abna0617046484'))->toBe('NL96 ABNA 0617 0464 84');
    });

    it('are editable on the Tarieven page', function () {
        Livewire::test(Rates::class)
            ->assertSchemaStateSet(['bank_iban' => 'NL96 ABNA 0617 0464 84'])
            ->set('data.bank_iban', 'be68539007547034')
            ->set('data.bank_account_holder', 'Stichting Test')
            ->call('save')
            ->assertHasNoErrors();

        expect(Setting::get(Setting::BANK_IBAN))->toBe('BE68 5390 0754 7034')
            ->and(Setting::get(Setting::BANK_ACCOUNT_HOLDER))->toBe('Stichting Test');
    });

    it('refuses an invalid IBAN', function () {
        Livewire::test(Rates::class)
            ->set('data.bank_iban', 'NL00 ABNA 0000 0000 00')
            ->call('save')
            ->assertHasErrors(['data.bank_iban']);
    });
});
