<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Persoonsgegevens')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('full_name')->label('Naam'),
                        TextEntry::make('display_number')->label('Lidnummer')->copyable(),
                        TextEntry::make('birthday')->label('Verjaardag')->date()->placeholder('—'),
                    ]),

                Section::make('Contact')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email')->label('E-mailadres')->placeholder('Geen e-mailadres')->copyable(),
                        TextEntry::make('phone_mobile')->label('Mobiel')->placeholder('—'),
                        TextEntry::make('phone_home')->label('Telefoon thuis')->placeholder('—'),
                    ]),

                Section::make('Adres')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('street')->label('Adres')->placeholder('—'),
                        TextEntry::make('postal_code')->label('Postcode')->placeholder('—'),
                        TextEntry::make('city')->label('Woonplaats')->placeholder('—'),
                        TextEntry::make('country')->label('Land')->placeholder('—'),
                    ]),

                Section::make('Lidmaatschap')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('club.name')->label('Vereniging')->placeholder('Geen'),
                        TextEntry::make('contact_for')->label('Contactpersoon van')->placeholder('—'),
                        TextEntry::make('joined_at')->label('Lid sinds')->date()->placeholder('—'),
                        TextEntry::make('cancelled_at')
                            ->label('Opgezegd op')
                            ->date()
                            ->badge()
                            ->color('danger')
                            ->visible(fn (Member $record): bool => $record->isCancelled()),
                        TextEntry::make('notes')->label('Notities')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
