<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * A user who has finished MFA setup, so the "MFA required" redirect doesn't apply.
 */
function userWithMfa(string $state = 'viewer'): User
{
    $factory = User::factory();

    if ($state !== 'viewer') {
        $factory = $factory->{$state}();
    }

    return $factory->create(['has_email_authentication' => true]);
}

it('redirects guests to the login page', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('shows the login page in Dutch', function () {
    get('/admin/login')->assertOk()->assertSee('Inloggen');
});

it('lets users without MFA in while MFA is optional', function () {
    config(['auth.admin_mfa_required' => false]);
    actingAs(User::factory()->admin()->create());

    get('/admin')->assertOk();
});

it('forces users without MFA to set it up first when MFA is required', function () {
    // The requirement is read when the panel routes are registered, so boot the app again with it switched on.
    $_ENV['ADMIN_MFA_REQUIRED'] = $_SERVER['ADMIN_MFA_REQUIRED'] = 'true';
    $this->refreshApplication();
    unset($_ENV['ADMIN_MFA_REQUIRED'], $_SERVER['ADMIN_MFA_REQUIRED']);
    $this->artisan('migrate');

    actingAs(User::factory()->admin()->create());

    get('/admin')->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('lets users with MFA reach the dashboard', function () {
    actingAs(userWithMfa());

    get('/admin')->assertOk();
});

it('only lets admins manage users', function (string $role, bool $allowed) {
    actingAs(userWithMfa($role));

    get(ListUsers::getUrl())->assertStatus($allowed ? 200 : 403);
})->with([
    'admin' => ['admin', true],
    'treasurer' => ['treasurer', false],
    'viewer' => ['viewer', false],
]);

it('does not let an admin delete or reset MFA for themselves', function () {
    $admin = userWithMfa('admin');
    $other = User::factory()->create();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('resetMfa', $admin))->toBeFalse()
        ->and($admin->can('delete', $other))->toBeTrue()
        ->and($admin->can('resetMfa', $other))->toBeTrue();
});

it('lets admins and treasurers manage payments, not viewers', function () {
    expect(User::factory()->admin()->make()->canManagePayments())->toBeTrue()
        ->and(User::factory()->treasurer()->make()->canManagePayments())->toBeTrue()
        ->and(User::factory()->make()->canManagePayments())->toBeFalse();
});

it('stores the MFA secret encrypted', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');
});

it('uses APP_NAME as the brand name in the panel', function () {
    config(['app.name' => 'Ledenbeheer Test']);

    expect(Filament::getPanel('admin')->getBrandName())->toBe('Ledenbeheer Test');
});
