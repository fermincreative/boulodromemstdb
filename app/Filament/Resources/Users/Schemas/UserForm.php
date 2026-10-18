<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-mailadres')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('role')
                    ->label('Rol')
                    ->options(UserRole::class)
                    ->default(UserRole::Viewer)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText(fn (?User $record): ?string => $record?->is(auth()->user())
                        ? 'U kunt uw eigen rol niet wijzigen.'
                        : null),
                TextInput::make('password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->rule(Password::min(12))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Laat leeg om het huidige wachtwoord te behouden.'
                        : 'Minimaal 12 tekens. De gebruiker stelt bij de eerste keer inloggen tweestapsverificatie in.'),
            ]);
    }
}
