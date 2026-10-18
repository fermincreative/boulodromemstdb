<?php

use App\Filament\Resources\CardYearStyles\CardYearStyleResource;
use App\Filament\Resources\CardYearStyles\Pages\ManageCardYearStyles;
use App\Models\CardYearStyle;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\CardYearStyles;
use App\Services\MembershipCard;
use App\Support\Color;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

it('gives 2027 a white-on-black year badge', function () {
    expect(app(CardYearStyles::class)->for(2027))->toBe(['badge' => '#000000', 'text' => '#FFFFFF']);
});

it('falls back to the beige accent for years without their own colours', function () {
    expect(app(CardYearStyles::class)->for(2026))->toBe(['badge' => '#E5D0A7', 'text' => '#000000']);
});

it('renders cards for a year with its own colours', function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    $membership = Membership::factory()->forYear(2027)->create();

    expect(app(MembershipCard::class)->phonePdf($membership))->toStartWith('%PDF-');
});

it('lets admins add colours for a new year', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCardYearStyles::class)
        ->callAction(TestAction::make('create')->table(), ['year' => 2028, 'badge_color' => '#1D4ED8', 'text_color' => '#FFFFFF'])
        ->assertHasNoFormErrors();

    expect(app(CardYearStyles::class)->for(2028))->toBe(['badge' => '#1D4ED8', 'text' => '#FFFFFF']);
});

it('refuses colours that make the year hard to read', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCardYearStyles::class)
        ->callAction(TestAction::make('create')->table(), ['year' => 2028, 'badge_color' => '#E5D0A7', 'text_color' => '#FFFFFF'])
        ->assertHasFormErrors(['text_color']);

    expect(CardYearStyle::where('year', 2028)->exists())->toBeFalse();
});

it('allows only one colour setting per year', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCardYearStyles::class)
        ->callAction(TestAction::make('create')->table(), ['year' => 2027, 'badge_color' => '#000000', 'text_color' => '#FFFFFF'])
        ->assertHasFormErrors(['year' => 'unique']);
});

it('keeps the card colours for admins only', function (string $role) {
    actingAs(User::factory()->{$role}()->create());

    get(CardYearStyleResource::getUrl())->assertForbidden();
})->with(['treasurer', 'viewer']);

it('shows the year in its colours on the verify page', function () {
    config(['membership_card.qr_secret' => 'test-secret']);
    travelTo(now()->setDate(2027, 3, 1));
    $membership = Membership::factory()->forYear(2027)->for(Member::factory())->create();

    parse_str(parse_url(app(MembershipCard::class)->qrPayload($membership), PHP_URL_QUERY), $params);

    get(route('card.verify', $params))
        ->assertSee('Geldig lid')
        ->assertSee('background: #000000; color: #FFFFFF', false);
});

it('computes WCAG contrast', function () {
    expect(round(Color::contrast('#000000', '#FFFFFF'), 1))->toBe(21.0)
        ->and(Color::contrast('#E5D0A7', '#000000'))->toBeGreaterThan(CardYearStyles::MIN_CONTRAST)
        ->and(Color::contrast('#E5D0A7', '#FFFFFF'))->toBeLessThan(CardYearStyles::MIN_CONTRAST);
});
