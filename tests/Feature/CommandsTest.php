<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\artisan;

it('creates an admin user from the command line', function () {
    artisan('users:create', [
        '--name' => 'Penningmeester',
        '--email' => 'penning@example.com',
        '--role' => 'treasurer',
        '--password' => 'een-lang-wachtwoord',
    ])->assertSuccessful();

    expect(User::firstWhere('email', 'penning@example.com'))
        ->role->toBe(UserRole::Treasurer);
});

it('rejects short passwords and unknown roles', function () {
    artisan('users:create', [
        '--name' => 'X',
        '--email' => 'x@example.com',
        '--role' => 'superuser',
        '--password' => 'kort',
    ])->assertFailed();

    expect(User::count())->toBe(0);
});
