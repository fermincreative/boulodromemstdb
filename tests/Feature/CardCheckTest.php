<?php

use App\Actions\Memberships\RenewMembership;
use App\Actions\Payments\CollectAtEntrance;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendMembershipCard;
use App\Models\Club;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipCard;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    Queue::fake();
    travelTo(now()->setDate(2027, 1, 10)->setTime(19, 30)); // a club evening in January
});

/**
 * The address a scanned card opens.
 */
function scanUrl(Membership $membership): string
{
    return app(MembershipCard::class)->qrPayload($membership);
}

function memberWithCard(int $year = 2027, string $club = 'SBM Halpas', array $attributes = []): Membership
{
    $member = Member::factory()->create(['email' => 'jan@example.nl', 'phone_mobile' => '06 1234 5678', 'club_id' => Club::firstWhere('name', $club)->id, ...$attributes]);

    return Membership::factory()->forYear($year)->for($member)->create();
}

describe('for the public', function () {
    it('only shows whether the card is valid', function () {
        $card = memberWithCard();

        get(scanUrl($card))
            ->assertOk()
            ->assertSee('Geldig lid')
            ->assertDontSee('jan@example.nl')
            ->assertDontSee('06 1234 5678')
            ->assertDontSee('aria-label="Beheer"', false)
            ->assertSee('Beheerder? Inloggen')
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('cannot use the staff actions', function () {
        $card = memberWithCard(2026);
        app(RenewMembership::class)->handle($card->member, 2027, false);
        $payment = RenewMembership::openPaymentFor($card->member, 2027);

        post(route('card.check.collect', $payment), ['return' => scanUrl($card)])->assertRedirect('/');
        expect($payment->fresh()->status)->toBe(PaymentStatus::Open);
    });
});

describe('for staff logged in on the same phone', function () {
    it('shows contact details and links into the panel', function () {
        actingAs(User::factory()->viewer()->create(['name' => 'Piet']));
        $card = memberWithCard();

        get(scanUrl($card))
            ->assertSee('Geldig lid')
            ->assertSee('Beheer')
            ->assertSee('Piet')
            ->assertSee('mailto:jan@example.nl', false)
            ->assertSee('tel:0612345678', false)
            ->assertSee('Openen in ledenadministratie')
            ->assertDontSee('Gegevens wijzigen')
            ->assertDontSee('Beheerder? Inloggen');
    });

    it('lets admins open the edit page', function () {
        actingAs(User::factory()->admin()->create());
        $card = memberWithCard();

        get(scanUrl($card))->assertSee('Gegevens wijzigen')->assertSee('/admin/leden/'.$card->member_id.'/edit', false);
    });

    it('takes a cash payment at the door: the member is valid at once', function () {
        actingAs($treasurer = User::factory()->treasurer()->create());
        $card = memberWithCard(2026);
        app(RenewMembership::class)->handle($card->member, 2027, false);
        $payment = RenewMembership::openPaymentFor($card->member, 2027);

        get(scanUrl($card))->assertSee('Lidmaatschap verlopen')->assertSee('Openstaand: Contributie 2027')->assertSee('Contant ontvangen');

        post(route('card.check.collect', $payment), ['return' => scanUrl($card)])->assertRedirect(scanUrl($card));

        expect($payment->fresh())
            ->status->toBe(PaymentStatus::Paid)
            ->method->toBe(PaymentMethod::Cash)
            ->reference->toBe(CollectAtEntrance::REFERENCE);
        Queue::assertPushed(SendMembershipCard::class);

        get(scanUrl($card))->assertSee('Geldig lid')->assertSee('Contant ontvangen: € 90,00. Het lidmaatschap is actief.');
    });

    it('renews and takes the cash in one go when the member was not renewed yet', function () {
        actingAs(User::factory()->treasurer()->create());
        $card = memberWithCard(2026);

        get(scanUrl($card))->assertSee('Nog niet verlengd voor 2027.')->assertSee('Verlengen voor 2027 en € 90,00 contant ontvangen');

        post(route('card.check.renew', $card->member), ['return' => scanUrl($card)])->assertRedirect(scanUrl($card));

        $membership = $card->member->memberships()->whereDate('starts_at', '2027-01-01')->sole();
        expect($membership->status)->toBe(MembershipStatus::Active)
            ->and($membership->payment->method)->toBe(PaymentMethod::Cash);

        get(scanUrl($card))->assertSee('Geldig lid')->assertSee('Verlengd voor 2027 en contant ontvangen: € 90,00.');
    });

    it('does not offer payment for members whose club pays', function () {
        actingAs(User::factory()->treasurer()->create());
        $card = memberWithCard(2026, 'Mosa Petanca');

        get(scanUrl($card))
            ->assertDontSee('Verlengen voor 2027')
            ->assertSee('De contributie wordt betaald door Mosa Petanca');

        post(route('card.check.renew', $card->member), ['return' => scanUrl($card)])
            ->assertSessionHas('card_check_error', 'Dit lid kan niet voor 2027 worden verlengd.');
    });

    it('keeps payments out of reach of viewers', function () {
        actingAs(User::factory()->viewer()->create());
        $card = memberWithCard(2026);
        app(RenewMembership::class)->handle($card->member, 2027, false);
        $payment = RenewMembership::openPaymentFor($card->member, 2027);

        get(scanUrl($card))->assertSee('Openstaand: Contributie 2027')->assertDontSee('Contant ontvangen</button>', false);

        post(route('card.check.collect', $payment), ['return' => scanUrl($card)])->assertForbidden();
        post(route('card.check.renew', $card->member), ['return' => scanUrl($card)])->assertForbidden();
        expect($payment->fresh()->status)->toBe(PaymentStatus::Open);
    });

    it('never sends staff to an outside address after an action', function () {
        actingAs(User::factory()->treasurer()->create());
        $card = memberWithCard(2026);

        post(route('card.check.renew', $card->member), ['return' => 'https://evil.example/verify?x=1'])->assertRedirect(url('/verify'));
    });
});

it('logs staff in through the admin login and brings them back to the card', function () {
    $card = memberWithCard();

    get(route('card.check.login', ['return' => scanUrl($card)]))
        ->assertRedirect(url('/admin/login'))
        ->assertSessionHas('url.intended', scanUrl($card));

    get(route('card.check.login', ['return' => 'https://evil.example']))->assertSessionHas('url.intended', url('/verify'));
});
