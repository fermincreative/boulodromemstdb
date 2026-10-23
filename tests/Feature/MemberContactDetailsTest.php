<?php

use App\Mail\ConfirmEmailChangeMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Membership;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
});

function memberWithLogin(array $attributes = []): Member
{
    $member = Member::factory()->create([
        'email' => 'jan@example.nl', 'phone_mobile' => '0611111111', 'phone_home' => null,
        'street' => 'Boschstraat 1', 'postal_code' => '6211 AS', 'city' => 'Maastricht', 'country' => 'Nederland',
        ...$attributes,
    ]);
    Membership::factory()->forYear(2026)->for($member)->create();

    return $member->fresh();
}

function details(array $overrides = []): array
{
    return [
        'email' => 'jan@example.nl', 'phone_mobile' => '0622222222', 'phone_home' => '043 1234567',
        'street' => 'Markt 2', 'postal_code' => '6211 CK', 'city' => 'Maastricht', 'country' => 'Nederland',
        ...$overrides,
    ];
}

it('offers "Wijzigen" next to the details', function () {
    $member = memberWithLogin();
    actingAs($member->account, 'member');

    get('/')->assertSee('Uw gegevens')->assertSee(route('portal.edit', $member), false)->assertSee('Wijzigen');
    get(route('portal.edit', $member))->assertOk()->assertSee('Gegevens wijzigen')->assertSee('value="0611111111"', false);
});

it('saves phone numbers and address right away, logged as the member', function () {
    $member = memberWithLogin();
    actingAs($member->account, 'member');

    put(route('portal.update', $member), details())->assertRedirect('/');
    get('/')->assertSee('Uw gegevens zijn opgeslagen.');

    expect($member->fresh())
        ->phone_mobile->toBe('0622222222')
        ->phone_home->toBe('043 1234567')
        ->street->toBe('Markt 2')
        ->postal_code->toBe('6211 CK');

    $log = Activity::where('log_name', 'members')->where('event', 'updated')->latest('id')->first();
    expect($log->causer->is($member->account))->toBeTrue()
        ->and(array_keys($log->properties['attributes']))->toEqualCanonicalizing(['phone_mobile', 'phone_home', 'street', 'postal_code']);
    Mail::assertNothingSent();
});

it('validates in Dutch and keeps what was typed', function () {
    $member = memberWithLogin();
    actingAs($member->account, 'member');

    put(route('portal.update', $member), details(['street' => '', 'email' => 'jan@example']))
        ->assertSessionHasErrors(['street' => 'Straat en huisnummer is verplicht.', 'email']);

    expect($member->fresh()->street)->toBe('Boschstraat 1');
});

describe('a new e-mail address', function () {
    it('is only used after confirming it from the new mailbox', function () {
        $member = memberWithLogin();
        $account = $member->account;
        actingAs($account, 'member');

        put(route('portal.update', $member), details(['email' => 'Jan.Nieuw@Example.nl']))->assertRedirect('/');
        get('/')->assertSee('We hebben een e-mail gestuurd naar <strong>jan.nieuw@example.nl</strong>', false);

        expect($member->fresh()->email)->toBe('jan@example.nl');

        $url = null;
        Mail::assertSent(ConfirmEmailChangeMail::class, function (ConfirmEmailChangeMail $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('jan.nieuw@example.nl');
        });

        // Opening the link (also by a mail scanner) changes nothing yet.
        get($url)->assertOk()->assertSee('Klik op de knop om het e-mailadres');
        expect($member->fresh()->email)->toBe('jan@example.nl');

        post($url)->assertRedirect('/');
        get('/')->assertSee('Uw e-mailadres is gewijzigd in jan.nieuw@example.nl.');

        expect($member->fresh()->email)->toBe('jan.nieuw@example.nl')
            ->and($account->fresh()->email)->toBe('jan.nieuw@example.nl')
            ->and(auth('member')->id())->toBe($account->id);

        get($url)->assertSee('Deze bevestigingslink werkt niet meer');
    });

    it('cannot be confirmed with a tampered or expired link', function () {
        $member = memberWithLogin();
        actingAs($member->account, 'member');
        put(route('portal.update', $member), details(['email' => 'nieuw@example.nl']));
        $url = null;
        Mail::assertSent(ConfirmEmailChangeMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        post(str_replace('nieuw%40example.nl', 'aanvaller%40example.nl', $url))->assertForbidden();

        travelTo(now()->addHours(49));
        post($url)->assertForbidden();

        expect($member->fresh()->email)->toBe('jan@example.nl');
    });

    it('gives a member their own login when they leave a shared address', function () {
        $jan = memberWithLogin(['first_name' => 'Jan']);
        $marie = memberWithLogin(['first_name' => 'Marie']);
        $shared = $jan->account;
        actingAs($shared, 'member');

        get(route('portal.edit', $marie))->assertSee('Dit e-mailadres gebruikt u samen met Jan.');

        put(route('portal.update', $marie), details(['email' => 'marie@example.nl']));
        Mail::assertSent(ConfirmEmailChangeMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        post($url);

        expect($jan->fresh()->member_account_id)->toBe($shared->id)
            ->and($marie->fresh()->account->email)->toBe('marie@example.nl')
            ->and(MemberAccount::count())->toBe(2);
    });
});

it('only lets members change their own details, while they are a member', function () {
    $mine = memberWithLogin();
    $other = memberWithLogin(['email' => 'ander@example.nl']);
    actingAs($mine->account, 'member');

    get(route('portal.edit', $other))->assertNotFound();
    put(route('portal.update', $other), details(['email' => 'ander@example.nl']))->assertNotFound();
    expect($other->fresh()->phone_mobile)->toBe('0611111111');

    $mine->update(['cancelled_at' => '2026-06-30']);
    get('/')->assertDontSee(route('portal.edit', $mine), false);
    get(route('portal.edit', $mine))->assertNotFound();
});

it('needs a login', function () {
    $member = memberWithLogin();

    get(route('portal.edit', $member))->assertRedirect('/');
    put(route('portal.update', $member), details())->assertRedirect('/');
});
