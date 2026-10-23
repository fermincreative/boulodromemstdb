<?php

use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentLink;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeader;

beforeEach(function () {
    Queue::fake();
    // Stand-in for lang/en/*.php, which does not exist yet.
    app('translator')->addLines(['signup.heading' => 'Become a member of :association', 'portal.login.title' => 'Log in'], 'en');
});

it('shows the public pages in Dutch, without a language switcher, while only Dutch is enabled', function () {
    get('/aanmelden?lang=en')->assertOk()->assertSee('Word lid van Boulodrôme Maastricht')->assertSee('<html lang="nl">', false)
        ->assertDontSee('aria-label="Taal / Language"', false);
});

it('switches language once English is enabled, and remembers the choice', function () {
    config(['app.public_locales' => ['nl', 'en']]);

    withHeader('Accept-Language', 'nl-NL,nl;q=0.9')->get('/aanmelden')->assertSee('Word lid van Boulodrôme Maastricht')->assertSee('English');
    get('/aanmelden?lang=en')->assertSee('Become a member of Boulodrôme Maastricht')->assertSee('<html lang="en">', false);
    get('/')->assertSee('Log in');
});

it('follows the browser language when nothing was chosen', function () {
    config(['app.public_locales' => ['nl', 'en']]);

    withHeader('Accept-Language', 'en-GB,en;q=0.9')->get('/aanmelden')->assertSee('Become a member of');
    withHeader('Accept-Language', 'fr-FR')->get('/aanmelden')->assertSee('Word lid van');
});

it('keeps the admin panel in Dutch', function () {
    config(['app.public_locales' => ['nl', 'en']]);
    get('/aanmelden?lang=en')->assertSessionHas('locale', 'en');
    // Every real request starts with the default language; tests reuse the application, so reset it like a new request.
    app()->setLocale('nl');
    actingAs(User::factory()->admin()->create());

    get('/admin')->assertOk()->assertSee('lang="nl"', false);
    expect(app()->getLocale())->toBe('nl');
});

it('can add the language to signed links without breaking them', function () {
    $payment = Payment::factory()->create();

    get(PaymentLink::for($payment).'&lang=nl')->assertOk();
});

it('has every Dutch text in every other language that is added', function () {
    $groups = ['signup', 'portal', 'payment'];
    $keys = fn (string $locale, string $group) => array_keys(Arr::dot(require lang_path("{$locale}/{$group}.php")));

    foreach (glob(lang_path('*'), GLOB_ONLYDIR) as $dir) {
        $locale = basename($dir);

        foreach ($groups as $group) {
            if ($locale === 'nl' || ! file_exists(lang_path("{$locale}/{$group}.php"))) {
                continue;
            }

            expect(array_diff($keys('nl', $group), $keys($locale, $group)))->toBe([], "lang/{$locale}/{$group}.php mist teksten");
        }
    }

    expect(file_exists(lang_path('nl/signup.php')))->toBeTrue();
});
