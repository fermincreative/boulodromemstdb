<?php

namespace App\Filament\Resources\Members\Actions;

use App\Actions\Members\ExportMembersCsv;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportMembersAction
{
    /**
     * Exports what the table shows right now (search, filters and sorting applied).
     */
    public static function make(): Action
    {
        return Action::make('exportCsv')
            ->label('Exporteren (CSV)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Ledenlijst exporteren')
            ->modalDescription('U downloadt de leden zoals ze nu in de tabel staan (met zoekopdracht en filters). Het bestand bevat persoonsgegevens: bewaar het veilig en verwijder het als u het niet meer nodig hebt.')
            ->modalSubmitActionLabel('Downloaden')
            ->authorize('export', Member::class)
            ->action(fn (HasTable $livewire): StreamedResponse => app(ExportMembersCsv::class)
                ->download($livewire->getTableQueryForExport(), 'tabel (zoekopdracht/filters)'));
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('exportSelectedCsv')
            ->label('Exporteren (CSV)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->requiresConfirmation()
            ->modalHeading('Geselecteerde leden exporteren')
            ->modalDescription('Het bestand bevat persoonsgegevens: bewaar het veilig en verwijder het als u het niet meer nodig hebt.')
            ->modalSubmitActionLabel('Downloaden')
            ->authorize('export', Member::class)
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records): StreamedResponse => app(ExportMembersCsv::class)
                ->download(Member::query()->whereKey($records->modelKeys())->orderBy('last_name')->orderBy('first_name'), 'selectie'));
    }
}
