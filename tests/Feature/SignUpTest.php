<?php

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendMemberMail;
use App\Mail\NewSignUpMail;
use App\Models\Club;
use App\Models\MailTemplate;
use App\Models\Member;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\followingRedirects;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    config(['mail.reply_to.address' => 'leden@example.nl', 'members.signup_notify_address' => null]);
    RateLimiter::clear('signup');
});

function signUpData(array $overrides = []): array
{
    return [
        'first_name' => 'Marie',
        'middle_name' => 'de',
        'last_name' => 'Vries',
        'email' => ' Marie.deVries@Example.nl ',
        'phone_mobile' => '06 12345678',
        'street' => 'Boschstraat 1',
        'postal_code' => '6211 AS',
        'city' => 'Maastricht',
        'country' => 'Nederland',
        'birthday' => '10/9/1962',
        'club_id' => Club::firstWhere('name', 'Mosa Petanca')->id,
        'consent' => '1',
        'website' => '',
        // Shown a minute ago, as a person would.
        'form_token' => Crypt::encryptString((string) now()->subMinute()->timestamp),
        ...$overrides,
    ];
}

it('shows the form with today\'s fee', function () {
    get('/aanmelden')
        ->assertOk()
        ->assertSee('Word lid van Boulodrôme Maastricht')
        ->assertSee('€ 22,50 (instap vanaf 1 oktober)')
        ->assertSee('Mosa Petanca');
});

it('creates the member with an open payment and shows the payment details', function () {
    followingRedirects()->post('/aanmelden', signUpData())
        ->assertOk()
        ->assertSee('Bedankt voor uw aanmelding, Marie!')
        ->assertSee('NL96 ABNA 0617 0464 84')
        ->assertSee('Stichting Boulodrôme Maastricht')
        ->assertSee('€ 22,50')
        ->assertSee('Contributie 2026 SBM-0001-95');

    $member = Member::sole();
    expect($member->full_name)->toBe('Marie de Vries')
        ->and($member->email)->toBe('marie.devries@example.nl')
        ->and($member->birthday->toDateString())->toBe('1962-09-10')
        ->and($member->club->name)->toBe('Mosa Petanca')
        ->and($member->joined_at->toDateString())->toBe('2026-10-18')
        ->and($member->memberships()->sole()->status)->toBe(MembershipStatus::Pending)
        ->and($member->payments()->sole()->status)->toBe(PaymentStatus::Open)
        ->and($member->payments()->sole()->amount_cents)->toBe(2250)
        ->and(Activity::where('event', 'signed_up')->exists())->toBeTrue();
});

it('sends the member the welcome e-mail and tells the ledenadministratie', function () {
    post('/aanmelden', signUpData());

    Queue::assertPushed(SendMemberMail::class, fn (SendMemberMail $job) => $job->templateKey === MailTemplate::WELCOME
        && str_contains($job->body, 'NL96 ABNA 0617 0464 84'));
    Mail::assertQueued(NewSignUpMail::class, fn (NewSignUpMail $mail) => $mail->hasTo('leden@example.nl'));
});

it('validates the form in Dutch', function (array $data, string $field, string $message) {
    post('/aanmelden', signUpData($data))
        ->assertSessionHasErrors([$field => $message]);

    expect(Member::count())->toBe(0);
})->with([
    'no name' => [['first_name' => ''], 'first_name', 'Voornaam is verplicht.'],
    'bad e-mail' => [['email' => 'marie@example'], 'email', 'Dit e-mailadres lijkt onvolledig. Controleer het deel na de @ (bijv. naam@voorbeeld.nl).'],
    'no e-mail' => [['email' => ''], 'email', 'E-mailadres is verplicht.'],
    'bad date' => [['birthday' => '31-02-1990'], 'birthday', 'Vul een geldige datum in, bijvoorbeeld 10-09-1992.'],
    'future birthday' => [['birthday' => '01-01-2030'], 'birthday', 'Controleer uw geboortedatum.'],
    'no consent' => [['consent' => null], 'consent', 'U moet akkoord gaan met het gebruik van uw gegevens voor de ledenadministratie.'],
    'unknown club' => [['club_id' => 9999], 'club_id', 'De gekozen vereniging is ongeldig.'],
]);

it('keeps what was typed when there are errors', function () {
    followingRedirects()->from('/aanmelden')->post('/aanmelden', signUpData(['email' => 'fout']))
        ->assertSee('value="Marie"', false)
        ->assertSee('Controleer de gemarkeerde velden.');
});

describe('spam protection', function () {
    it('ignores bots that fill in the hidden field', function () {
        post('/aanmelden', signUpData(['website' => 'http://spam.example']))->assertRedirect(route('signup.thanks'));

        expect(Member::count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('ignores forms submitted too fast or without a valid token', function (array $data) {
        post('/aanmelden', signUpData($data))->assertRedirect(route('signup.thanks'));

        expect(Member::count())->toBe(0);
    })->with([
        'instant' => fn () => ['form_token' => Crypt::encryptString((string) now()->timestamp)],
        'forged' => fn () => ['form_token' => 'abc'],
    ]);

    it('limits sign-ups per internet connection', function () {
        foreach (range(1, 5) as $i) {
            post('/aanmelden', signUpData(['email' => "lid{$i}@example.nl"]));
        }

        post('/aanmelden', signUpData(['email' => 'lid6@example.nl']))
            ->assertSessionHasErrors(['form']);

        expect(Member::count())->toBe(5);
    });
});

it('can be switched off', function () {
    config(['members.signup_enabled' => false]);

    get('/aanmelden')->assertOk()->assertSee('Aanmelden is op dit moment niet mogelijk')->assertDontSee('name="first_name"', false);
    post('/aanmelden', signUpData())->assertNotFound();
});

it('gives each sign-up its own member number', function () {
    Member::factory()->create(['member_number' => 41]);

    post('/aanmelden', signUpData());
    post('/aanmelden', signUpData(['email' => 'ander@example.nl']));

    expect(Member::orderBy('member_number')->pluck('member_number')->all())->toBe([41, 42, 43]);
});

it('shows a general thank-you without session data', function () {
    get('/aanmelden/bedankt')->assertOk()->assertSee('Bedankt voor uw aanmelding!')->assertDontSee('Rekeningnummer');
});
