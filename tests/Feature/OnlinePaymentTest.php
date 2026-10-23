<?php

use App\Actions\Members\CreateMember;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendMemberMail;
use App\Jobs\SendMembershipCard;
use App\Models\MailTemplate;
use App\Models\Payment;
use App\Support\PaymentLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['services.mollie.key' => 'test_abc', 'membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
});

function openPayment(): Payment
{
    return app(CreateMember::class)
        ->handle(['first_name' => 'Jan', 'last_name' => 'Jansen', 'email' => 'jan@example.nl'], withMembership: true)
        ->payments()->sole();
}

describe('payment page', function () {
    it('shows the amount, an iDEAL button and the bank details', function () {
        $payment = openPayment();

        get(PaymentLink::for($payment))
            ->assertOk()
            ->assertSee('€ 22,50')
            ->assertSee('Jan Jansen (SBM-0001-95)')
            ->assertSee('Betalen met iDEAL of Bancontact')
            ->assertSee('NL96 ABNA 0617 0464 84')
            ->assertSee('Contributie 2026 SBM-0001-95');

        Http::assertNothingSent();
    });

    it('needs a valid signature', function () {
        $payment = openPayment();

        get("/betalen/{$payment->id}")->assertForbidden();
        get(PaymentLink::for($payment).'x')->assertForbidden();
        post("/betalen/{$payment->id}")->assertForbidden();
    });

    it('only offers bank transfer without a Mollie key', function () {
        config(['services.mollie.key' => null]);
        $payment = openPayment();

        get(PaymentLink::for($payment))->assertOk()->assertDontSee('Betalen met iDEAL')->assertSee('Betalen per overboeking');
    });

    it('says when the payment was already made or withdrawn', function () {
        $paid = openPayment();
        $paid->update(['status' => PaymentStatus::Paid]);

        get(PaymentLink::for($paid))->assertSee('Betaling ontvangen')->assertDontSee('Betalen met iDEAL');

        $paid->update(['status' => PaymentStatus::Cancelled]);
        get(PaymentLink::for($paid))->assertSee('Deze betaling staat niet meer open');
    });
});

describe('starting a payment', function () {
    it('creates a Mollie payment and sends the member to the checkout', function () {
        Http::fake(['api.mollie.com/v2/payments' => Http::response(molliePayment('tr_1', 'open', '22.50', null))]);
        $payment = openPayment();

        post(PaymentLink::start($payment))->assertRedirect('https://www.mollie.com/checkout/tr_1');

        expect($payment->fresh()->mollie_id)->toBe('tr_1');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test_abc')
            && $request['description'] === 'Contributie 2026 (instap vanaf 1 oktober) SBM-0001-95'
            && $request['locale'] === 'nl_NL');
    });

    it('continues an unfinished checkout instead of starting a new one', function () {
        Http::fake(['api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', 'open', '22.50', null))]);
        $payment = openPayment();
        $payment->update(['mollie_id' => 'tr_1']);

        post(PaymentLink::start($payment))->assertRedirect('https://www.mollie.com/checkout/tr_1');

        Http::assertSentCount(1);
    });

    it('starts again after a failed attempt', function () {
        Http::fake([
            'api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', 'expired', '22.50', null)),
            'api.mollie.com/v2/payments' => Http::response(molliePayment('tr_2', 'open', '22.50', null)),
        ]);
        $payment = openPayment();
        $payment->update(['mollie_id' => 'tr_1']);

        post(PaymentLink::start($payment))->assertRedirect('https://www.mollie.com/checkout/tr_2');

        expect($payment->fresh()->mollie_id)->toBe('tr_2');
    });

    it('shows a message when Mollie cannot be reached', function () {
        Http::fake(['api.mollie.com/*' => Http::response([], 503)]);
        $payment = openPayment();

        post(PaymentLink::start($payment))->assertOk()->assertSee('Online betalen lukt op dit moment niet');
    });
});

describe('settling', function () {
    it('activates the membership and e-mails confirmation and card when the member returns', function () {
        $payment = openPayment();
        $payment->update(['mollie_id' => 'tr_1']);
        Http::fake(['api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', 'paid', '22.50', $payment->id))]);

        get(PaymentLink::return($payment))->assertOk()->assertSee('Betaling ontvangen');

        $payment->refresh();
        expect($payment->status)->toBe(PaymentStatus::Paid)
            ->and($payment->method)->toBe(PaymentMethod::Ideal)
            ->and($payment->reference)->toBe('tr_1')
            ->and($payment->paid_at->toDateString())->toBe('2026-10-20')
            ->and($payment->memberships()->sole()->status)->toBe(MembershipStatus::Active);
        Queue::assertPushed(SendMembershipCard::class, 1);
        Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::PAYMENT_CONFIRMATION
            && str_contains($job->body, '€ 22,50'));
    });

    it('records Bancontact when the member paid with Bancontact', function () {
        $payment = openPayment();
        Http::fake(['api.mollie.com/*' => Http::response(molliePayment('tr_1', 'paid', '22.50', $payment->id, ['method' => 'bancontact']))]);

        post('/mollie/webhook', ['id' => 'tr_1'])->assertOk();

        expect($payment->fresh()->method)->toBe(PaymentMethod::Bancontact)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    });

    it('offers only the configured methods', function () {
        config(['services.mollie.methods' => 'ideal']);
        $payment = openPayment();

        get(PaymentLink::for($payment))->assertSee('Betalen met iDEAL')->assertDontSee('Bancontact');
    });

    it('settles through the webhook, only once', function () {
        $payment = openPayment();
        Http::fake(['api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', 'paid', '22.50', $payment->id))]);

        post('/mollie/webhook', ['id' => 'tr_1'])->assertOk();
        post('/mollie/webhook', ['id' => 'tr_1'])->assertOk();
        get(PaymentLink::return($payment->fresh()))->assertSee('Betaling ontvangen');

        expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
        Queue::assertPushed(SendMembershipCard::class, 1);
        expect(Activity::where('event', 'paid')->count())->toBe(1);
    });

    it('tells the member a payment is still being processed, or failed', function (string $status, string $message) {
        $payment = openPayment();
        $payment->update(['mollie_id' => 'tr_1']);
        Http::fake(['api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', $status, '22.50', $payment->id))]);

        get(PaymentLink::return($payment))->assertSee($message);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Open);
    })->with([
        'pending' => ['pending', 'Uw betaling wordt nog verwerkt'],
        'canceled' => ['canceled', 'De betaling is niet gelukt of afgebroken'],
        'expired' => ['expired', 'De betaling is niet gelukt of afgebroken'],
    ]);

    it('does not trust anything but Mollie itself', function () {
        $payment = openPayment();
        Http::fake(['api.mollie.com/v2/payments/tr_1' => Http::response(molliePayment('tr_1', 'open', '22.50', $payment->id))]);

        post('/mollie/webhook', ['id' => 'tr_1', 'status' => 'paid'])->assertOk();
        post('/mollie/webhook', ['id' => 'nonsense'])->assertOk();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Open);
        Http::assertSentCount(1);
    });

    it('answers unknown payment ids without retrying', function () {
        Http::fake(['api.mollie.com/*' => Http::response(['status' => 404], 404)]);

        post('/mollie/webhook', ['id' => 'tr_unknown'])->assertOk();
    });

    it('asks Mollie to retry when Mollie cannot be reached', function () {
        Http::fake(['api.mollie.com/*' => Http::response([], 502)]);

        post('/mollie/webhook', ['id' => 'tr_1'])->assertStatus(503);
    });

    it('logs money for a withdrawn payment or a different amount instead of processing it', function (array $change, string $amount) {
        $payment = openPayment();
        $payment->update($change);
        Http::fake(['api.mollie.com/*' => Http::response(molliePayment('tr_1', 'paid', $amount, $payment->id))]);

        post('/mollie/webhook', ['id' => 'tr_1'])->assertOk();

        expect($payment->fresh()->status)->not->toBe(PaymentStatus::Paid)
            ->and(Activity::where('event', 'online_payment_unmatched')->exists())->toBeTrue();
        Queue::assertNotPushed(SendMembershipCard::class);
    })->with([
        'cancelled' => [['status' => PaymentStatus::Cancelled], '22.50'],
        'other amount' => [[], '1.00'],
    ]);
});
