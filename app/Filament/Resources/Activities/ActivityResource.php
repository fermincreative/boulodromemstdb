<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Tables\ActivityColumns;
use App\Models\User;
use App\Services\ActivityPresenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'logboek';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'gebeurtenis';

    protected static ?string $pluralModelLabel = 'logboek';

    protected static ?string $navigationLabel = 'Logboek';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 95;

    public static function table(Table $table): Table
    {
        return $table
            ->query(ActivityColumns::query())
            ->columns(ActivityColumns::make())
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Onderdeel')
                    ->options(ActivityPresenter::LOG_NAMES),
                SelectFilter::make('causer_id')
                    ->label('Door')
                    ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])
                        : $query),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
