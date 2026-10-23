<?php

namespace App\Filament\Tables;

use App\Models\Member;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

/**
 * The member's club; clubs that pay for their members stand out, so nobody sends those members a payment request.
 */
class ClubColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('club.name')
            ->label('Vereniging')
            ->placeholder('Geen')
            ->badge()
            ->color(fn (Member $record): string => $record->paysCollectively() ? 'info' : 'gray')
            ->icon(fn (Member $record): ?Heroicon => $record->paysCollectively() ? Heroicon::OutlinedUserGroup : null)
            ->tooltip(fn (Member $record): ?string => $record->paysCollectively() ? 'Contributie wordt betaald door de vereniging' : null)
            ->sortable();
    }
}
