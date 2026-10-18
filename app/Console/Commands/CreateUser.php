<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'users:create
        {--name= : Naam}
        {--email= : E-mailadres}
        {--role= : admin, treasurer of viewer}
        {--password= : Wachtwoord (anders wordt erom gevraagd)}';

    protected $description = 'Maak een gebruiker voor het beheerpaneel aan';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Naam', required: true),
            'email' => $this->option('email') ?? text('E-mailadres', required: true),
            'role' => $this->option('role') ?? select(
                'Rol',
                collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()])->all(),
                default: UserRole::Admin->value,
            ),
            'password' => $this->option('password') ?? password('Wachtwoord (minimaal 12 tekens)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->components->info("Gebruiker {$user->email} aangemaakt als {$user->role->getLabel()}. Tweestapsverificatie wordt bij de eerste keer inloggen ingesteld.");

        return self::SUCCESS;
    }
}
