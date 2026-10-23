<?php

use App\Actions\Accounts\LinkMemberAccount;
use App\Actions\Members\CreateMember;
use App\Mail\ConfirmEmailChangeMail;
use App\Mail\EmailChangedNoticeMail;
use App\Mail\MemberMail;
use App\Models\Club;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\MailTemplates;
use App\Services\MembershipCard;
use App\Support\Markdown;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
    RateLimiter::clear('signup');
});

function establishedMember(string $email = 'jan@example.nl', array $attributes = []): Member
{
    $member = Member::factory()->create(['email' => $email, 'street' => 'Markt 1', 'postal_code' => '6211 CK', 'city' => 'Maastricht', 'country' => 'Nederland', ...$attributes]);
    Membership::factory()->forYear(2026)->for($member)->create();
    app(LinkMemberAccount::class)->forMember($member);

    return $member->fresh();
}

function signUpAs(array $overrides): TestResponse
{
    return post('/aanmelden', [
        'first_name' => 'Marie', 'last_name' => 'Jansen', 'email' => 'marie@example.nl',
        'street' => 'Markt 1', 'postal_code' => '6211 CK', 'city' => 'Maastricht', 'country' => 'Nederland',
        'consent' => '1', 'website' => '', 'form_token' => Crypt::encryptString((string) now()->subMinute()->timestamp),
        ...$overrides,
    ]);
}

describe('links in e-mails through names', function () {
    it('only accepts real names on the sign-up form', function (string $field, string $value) {
        signUpAs([$field => $value])->assertSessionHasErrors([$field => 'Gebruik alleen letters, spaties, koppeltekens, apostroffen en punten.']);

        expect(Member::count())->toBe(0);
    })->with([
        'markdown link' => ['first_name', '[Betaal hier](https://evil.example)'],
        'web address' => ['last_name', 'https://evil.example'],
        'html' => ['first_name', '<b>Jan</b>'],
    ]);

    it('still accepts ordinary names from anywhere', function (string $first, string $last) {
        signUpAs(['first_name' => $first, 'last_name' => $last])->assertSessionHasNoErrors();
    })->with([['Anne-Marie', "D'Hondt"], ['José', 'Müller'], ['J.P.', 'van ’t Hof'], ['Zoë', 'Łukasiewicz']]);

    it('never turns a name into a link in an e-mail, also when entered in the panel', function () {
        $member = app(CreateMember::class)->handle(['first_name' => '[Betaal hier](https://evil.example/pay)', 'last_name' => 'X', 'email' => 'x@example.nl'], withMembership: true);
        $mail = app(MailTemplates::class)->renderWelcome($member->payments()->sole());

        $html = (new MemberMail($mail['subject'], $mail['body']))->render();

        expect($html)->not->toContain('href="https://evil.example/pay"')
            ->and($html)->toContain('[Betaal hier](https://evil.example/pay)')
            ->and($mail['subject'])->not->toContain('\\');
    });

    it('leaves ordinary names untouched', function () {
        expect(Markdown::escape("Anne-Marie D'Hondt-Jansen J.P."))->toBe("Anne-Marie D'Hondt-Jansen J.P.")
            ->and(Markdown::escape('[x](y) *z* <b>'))->toBe('\\[x\\]\\(y\\) \\*z\\* \\<b\\>');
    });
});

it('tells the old address when an e-mail address was changed', function () {
    $member = establishedMember();
    actingAs($member->account, 'member');

    put(route('portal.update', $member), ['email' => 'nieuw@example.nl', 'street' => 'Markt 1', 'postal_code' => '6211 CK', 'city' => 'Maastricht', 'country' => 'Nederland']);
    $url = null;
    Mail::assertSent(ConfirmEmailChangeMail::class, function ($mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    Mail::assertNotSent(EmailChangedNoticeMail::class);

    post($url);

    Mail::assertSent(EmailChangedNoticeMail::class, fn (EmailChangedNoticeMail $mail) => $mail->hasTo('jan@example.nl') && $mail->newEmail === 'nieuw@example.nl');
});

it('keeps a sign-up with someone else\'s address out of their ledenportaal until it is paid', function () {
    $jan = establishedMember();

    signUpAs(['first_name' => 'Indringer', 'email' => 'jan@example.nl'])->assertSessionHasNoErrors();

    expect(Member::firstWhere('first_name', 'Indringer')->member_account_id)->toBeNull();
    actingAs($jan->account, 'member');
    get('/')->assertDontSee('Indringer');
});

it('limits how often contact details can be changed', function () {
    $member = establishedMember();
    actingAs($member->account, 'member');
    $data = ['email' => 'jan@example.nl', 'street' => 'Markt 1', 'postal_code' => '6211 CK', 'city' => 'Maastricht', 'country' => 'Nederland'];

    foreach (range(1, 10) as $i) {
        put(route('portal.update', $member), $data)->assertSessionHasNoErrors();
    }

    put(route('portal.update', $member), $data)->assertSessionHasErrors(['email' => 'U heeft uw gegevens te vaak gewijzigd. Probeer het over een uur opnieuw.']);
});

describe('browser protection headers', function () {
    it('are sent on the public pages', function (string $path) {
        $response = get('https://localhost'.$path);

        expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")
            ->and($response->headers->get('Referrer-Policy'))->toBe('same-origin')
            ->and($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
    })->with(['/', '/aanmelden', '/verify']);

    it('are left to Filament in the admin panel', function () {
        actingAs(User::factory()->admin()->create());

        expect(get('/admin')->headers->get('Content-Security-Policy'))->toBeNull();
    });
});

it('escapes the member name in the card check confirmations', function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    actingAs(User::factory()->treasurer()->create());
    $member = Member::factory()->create(['first_name' => "O'Brien\"", 'last_name' => '</script>', 'club_id' => Club::firstWhere('name', 'SBM Halpas')->id]);
    $membership = Membership::factory()->forYear(2025)->for($member)->create();
    travelTo(now()->setDate(2026, 1, 10));

    get(app(MembershipCard::class)->qrPayload($membership))
        ->assertSee("return confirm('O\\u0027Brien\\u0022", false)
        ->assertDontSee('</script> verlengen', false);
});
