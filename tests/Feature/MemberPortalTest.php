<?php

use App\Actions\Members\CreateMember;
use App\Enums\MembershipStatus;
use App\Mail\MemberLoginLinkMail;
use App\Models\Club;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberLoginToken;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipCard;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
    config(['membership_card.qr_secret' => 'test-secret', 'mail.reply_to.address' => 'leden@example.nl']);
    RateLimiter::clear('member-login');
});

function portalMember(array $attributes = [], bool $paid = true): Member
{
    $member = Member::factory()->create(['email' => 'jan@example.nl', 'club_id' => Club::firstWhere('name', 'SBM Halpas')->id, ...$attributes]);
    Membership::factory()->forYear(2026)->for($member)->state(['status' => $paid ? MembershipStatus::Active : MembershipStatus::Pending])->create();

    return $member;
}

/**
 * Requests a link and returns the URL from the e-mail.
 */
function requestLoginLink(string $email = 'jan@example.nl'): ?string
{
    post('/inloggen', ['email' => $email]);
    $url = null;
    Mail::assertSent(MemberLoginLinkMail::class, function (MemberLoginLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    return $url;
}

function logInAs(string $email = 'jan@example.nl'): MemberAccount
{
    $url = requestLoginLink($email);
    post($url)->assertRedirect('/');

    return MemberAccount::where('email', $email)->sole();
}

describe('logging in', function () {
    it('shows the login on the main domain', function () {
        get('/')->assertOk()->assertSee('Stuur mij een inloglink')->assertSee('Aanmelden');
    });

    it('e-mails a link to a known address, creating the account', function () {
        $member = portalMember(['email' => 'Jan@Example.nl']);

        post('/inloggen', ['email' => ' JAN@example.nl '])->assertRedirect('/');
        get('/')->assertSee('Als <strong>jan@example.nl</strong> bij ons bekend is', false);

        Mail::assertSent(MemberLoginLinkMail::class, fn (MemberLoginLinkMail $mail) => $mail->hasTo('jan@example.nl')
            && str_contains($mail->url, '/inloggen/'));
        expect($member->fresh()->account->email)->toBe('jan@example.nl')
            ->and(MemberLoginToken::sole()->token_hash)->toHaveLength(64);
    });

    it('gives nothing away about unknown addresses', function () {
        portalMember();

        post('/inloggen', ['email' => 'onbekend@example.nl'])->assertRedirect('/');
        get('/')->assertSee('Als <strong>onbekend@example.nl</strong> bij ons bekend is', false);

        Mail::assertNothingSent();
        expect(MemberAccount::where('email', 'onbekend@example.nl')->exists())->toBeFalse();
    });

    it('opening the link only shows a button, so mail scanners cannot use it up', function () {
        portalMember();
        $url = requestLoginLink();

        get($url)->assertOk()->assertSee('Klik op de knop om in te loggen');
        get($url)->assertOk()->assertSee('Klik op de knop om in te loggen');

        expect(auth('member')->check())->toBeFalse()
            ->and(MemberLoginToken::sole()->used_at)->toBeNull();
    });

    it('logs in with the link, once', function () {
        portalMember();
        $url = requestLoginLink();

        post($url)->assertRedirect('/');

        expect(auth('member')->user()->email)->toBe('jan@example.nl')
            ->and(auth('member')->user()->last_login_at)->not->toBeNull()
            ->and(Activity::where('event', 'logged_in')->exists())->toBeTrue();

        auth('member')->logout();
        post($url)->assertRedirect('/')->assertSessionHasErrors(['email' => 'Deze inloglink is verlopen of al gebruikt. Vraag hieronder een nieuwe aan.']);
        get($url)->assertSee('Deze inloglink werkt niet meer');
        expect(auth('member')->check())->toBeFalse();
    });

    it('refuses a link after 30 minutes', function () {
        portalMember();
        $url = requestLoginLink();
        travelTo(now()->addMinutes(31));

        post($url)->assertSessionHasErrors('email');
        expect(auth('member')->check())->toBeFalse();
    });

    it('refuses made-up links', function () {
        portalMember();
        requestLoginLink();

        post('/inloggen/'.str_repeat('a', 64))->assertSessionHasErrors('email');
        get('/inloggen/te-kort')->assertNotFound();
        expect(auth('member')->check())->toBeFalse();
    });

    it('limits how often links can be requested for an address', function () {
        portalMember();

        foreach (range(1, 5) as $i) {
            post('/inloggen', ['email' => 'jan@example.nl']);
        }
        post('/inloggen', ['email' => 'jan@example.nl'])->assertSessionHasErrors(['email' => 'U heeft te vaak een inloglink aangevraagd. Probeer het over een uur opnieuw.']);

        Mail::assertSentCount(5);
    });

    it('validates the address', function () {
        post('/inloggen', ['email' => 'geen-adres'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    });

    it('logs out the member only, not a staff login in the same browser', function () {
        portalMember();
        $admin = User::factory()->admin()->create();
        actingAs($admin);
        logInAs();

        post('/uitloggen')->assertRedirect('/');

        expect(auth('member')->check())->toBeFalse()
            ->and(auth('web')->id())->toBe($admin->id);
        get('/')->assertSee('U bent uitgelogd.');
    });
});

describe('accounts', function () {
    it('shares one login between members with the same address', function () {
        $jan = portalMember(['first_name' => 'Jan']);
        $marie = portalMember(['first_name' => 'Marie']);

        Mail::assertNothingSent();
        $account = logInAs();

        expect($account->members->pluck('id')->sort()->values()->all())->toBe(collect([$jan->id, $marie->id])->sort()->values()->all());
        Mail::assertSent(MemberLoginLinkMail::class, fn (MemberLoginLinkMail $mail) => $mail->account->greetingName() === 'Jan en Marie');
        get('/')->assertSee('Jan')->assertSee('Marie');
    });

    it('links new members, from the panel or the sign-up form, to the account of their address', function () {
        $jan = portalMember();
        $marie = app(CreateMember::class)->handle(['first_name' => 'Marie', 'last_name' => 'Jansen', 'email' => 'JAN@example.nl'], withMembership: true);

        expect($marie->fresh()->member_account_id)->toBe($jan->fresh()->member_account_id)
            ->and(MemberAccount::count())->toBe(1);
    });

    it('moves the account along when the only member on it changes address', function () {
        $member = portalMember();
        $account = $member->fresh()->account;
        requestLoginLink();

        $member->update(['email' => 'jan.nieuw@example.nl']);

        expect($account->fresh()->email)->toBe('jan.nieuw@example.nl')
            ->and($member->fresh()->member_account_id)->toBe($account->id)
            ->and(MemberLoginToken::count())->toBe(0);
    });

    it('splits a shared login when one of the members changes address', function () {
        $jan = portalMember(['first_name' => 'Jan']);
        $marie = portalMember(['first_name' => 'Marie']);
        $shared = $jan->fresh()->account;

        $marie->update(['email' => 'marie@example.nl']);

        expect($jan->fresh()->account->is($shared))->toBeTrue()
            ->and($shared->fresh()->email)->toBe('jan@example.nl')
            ->and($marie->fresh()->account->email)->toBe('marie@example.nl');
    });

    it('has no account without an e-mail address', function () {
        $member = portalMember(['email' => null]);

        expect($member->fresh()->account)->toBeNull();
    });
});

describe('the overview', function () {
    it('shows the member\'s own membership, details and card', function () {
        $member = portalMember(['phone_mobile' => '0612345678', 'street' => 'Boschstraat 1']);
        logInAs();

        get('/')
            ->assertOk()
            ->assertSee('Ingelogd als jan@example.nl')
            ->assertSee($member->full_name)
            ->assertSee($member->display_number)
            ->assertSee('U bent lid tot en met <strong>31-12-2026</strong>', false)
            ->assertSee('0612345678')
            ->assertSee('Boschstraat 1')
            ->assertSee('QR-code lidmaatschapskaart 2026')
            ->assertSee('Toon deze code bij de ingang')
            ->assertSee('Download voor telefoon')
            ->assertSee('leden@example.nl');
    });

    it('shows the latest paid date when next year is paid already, with this year\'s card until next year starts', function () {
        $member = portalMember();
        Membership::factory()->forYear(2027)->for($member)->create();
        logInAs();

        get('/')
            ->assertSee('U bent lid tot en met <strong>31-12-2027</strong>', false)
            ->assertSee('QR-code lidmaatschapskaart 2026')
            ->assertDontSee('QR-code lidmaatschapskaart 2027')
            ->assertSee('Uw lidmaatschap voor 2027 is al betaald. De kaart voor 2027 verschijnt hier op 01-01-2027.');

        travelTo(now()->setDate(2027, 1, 1));

        get('/')
            ->assertSee('U bent lid tot en met <strong>31-12-2027</strong>', false)
            ->assertSee('QR-code lidmaatschapskaart 2027')
            ->assertDontSee('is al betaald');
    });

    it('shows the same QR code content as on the printed card', function () {
        $member = portalMember();
        $membership = $member->memberships()->sole();
        $card = app(MembershipCard::class);

        $svg = $card->qrSvg($membership);
        $matrix = (new TCPDF2DBarcode($card->qrPayload($membership), 'QRCODE,M'))->getBarcodeArray();

        // Same content, error correction and size as on the PDF (TCPDF picks the mask pattern at random, so pixels may differ).
        $size = $matrix['num_cols'] + 8;
        expect($svg)->toStartWith('<svg ')
            ->and($svg)->toContain("viewBox=\"0 0 {$size} {$size}\"")
            ->and(substr_count($svg, 'h1v1h-1z'))->toBeGreaterThan(100)
            ->and($card->qrPayload($membership))->toContain('&c='.MembershipCard::signature($member->member_number, $member->full_name, 2026));
    });

    it('never shows other members', function () {
        portalMember();
        $other = portalMember(['email' => 'iemand.anders@example.nl', 'first_name' => 'Pietronella']);
        logInAs();

        get('/')->assertDontSee('Pietronella')->assertDontSee($other->display_number);
    });

    it('shows open payments with a pay button', function () {
        $member = portalMember(paid: false);
        $payment = $member->payments()->create(['description' => 'Contributie 2026', 'amount_cents' => 9000, 'status' => 'open']);
        $member->memberships()->update(['payment_id' => $payment->id]);
        logInAs();

        get('/')
            ->assertSee('Contributie 2026: <strong>€ 90,00</strong>', false)
            ->assertSee('/betalen/'.$payment->id.'?signature=', false)
            ->assertSee('wacht op uw betaling')
            ->assertDontSee('Toon deze code bij de ingang');
    });

    it('says when a club pays', function () {
        portalMember(['club_id' => Club::firstWhere('name', 'Mosa Petanca')->id], paid: false);
        logInAs();

        get('/')->assertSee('wordt betaald via Mosa Petanca');
    });

    it('tells former members their membership has ended', function () {
        $member = portalMember();
        $account = $member->fresh()->account;
        $member->update(['cancelled_at' => '2026-06-30']);

        actingAs($account, 'member');

        get('/')->assertSee('Uw lidmaatschap is beëindigd per 30-06-2026')->assertDontSee('Toon deze code bij de ingang');
    });
});

describe('cards', function () {
    it('lets members download their own card', function (string $variant) {
        $member = portalMember();
        logInAs();

        get(route('portal.card', [$member->memberships()->sole(), $variant]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    })->with(['phone', 'print']);

    it('does not hand out other members\' cards, or cards that are not valid', function () {
        portalMember();
        $other = portalMember(['email' => 'ander@example.nl']);
        $pending = Membership::factory()->forYear(2027)->pending()->for(Member::firstWhere('email', 'jan@example.nl'))->create();
        logInAs();

        get(route('portal.card', [$other->memberships()->sole(), 'phone']))->assertNotFound();
        get(route('portal.card', [$pending, 'phone']))->assertNotFound();
        get(route('portal.card', [Member::firstWhere('email', 'jan@example.nl')->memberships()->oldest('id')->first(), 'other']))->assertNotFound();
    });

    it('needs a login', function () {
        $member = portalMember();

        get(route('portal.card', [$member->memberships()->sole(), 'phone']))->assertRedirect('/');
    });
});

it('keeps member accounts out of the admin panel', function () {
    portalMember();
    logInAs();

    get('/admin')->assertRedirect('/admin/login');
});

it('cleans up old login links', function () {
    portalMember();
    requestLoginLink();
    travelTo(now()->addDays(2));

    $this->artisan('model:prune', ['--model' => [MemberLoginToken::class]]);

    expect(MemberLoginToken::count())->toBe(0);
});
