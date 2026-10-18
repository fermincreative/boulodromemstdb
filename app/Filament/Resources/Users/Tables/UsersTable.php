<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mailadres')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->sortable(),
                IconColumn::make('mfa')
                    ->label('Tweestapsverificatie')
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret) || $record->hasEmailAuthentication()),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->date('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('resetMfa')
                    ->label('MFA resetten')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('De gebruiker moet bij de volgende keer inloggen opnieuw tweestapsverificatie instellen. Gebruik dit bijvoorbeeld als iemand de telefoon kwijt is.')
                    ->visible(fn (User $record): bool => auth()->user()->can('resetMfa', $record))
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'app_authentication_secret' => null,
                            'app_authentication_recovery_codes' => null,
                            'has_email_authentication' => false,
                        ])->save();

                        Notification::make()->title('Tweestapsverificatie gereset')->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }
}
