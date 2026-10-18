<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Tables\ActivityColumns;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recente gebeurtenissen')
            ->query(ActivityColumns::query())
            ->columns(ActivityColumns::make())
            ->headerActions([
                Action::make('all')
                    ->label('Volledig logboek')
                    ->link()
                    ->url(ActivityResource::getUrl()),
            ])
            ->paginated([10])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Nog geen gebeurtenissen');
    }
}
