<?php

namespace App\Filament\Tables;

use App\Models\Membership;
use App\Models\Payment;
use App\Services\ActivityPresenter;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Activity;

/**
 * Columns shared by the dashboard widget and the Logboek page.
 */
class ActivityColumns
{
    /**
     * @return Builder<Activity>
     */
    public static function query(): Builder
    {
        return Activity::query()
            ->with([
                'causer',
                'subject' => fn (MorphTo $morph) => $morph->morphWith([Membership::class => ['member'], Payment::class => ['member']]),
            ])
            ->latest('id');
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function make(): array
    {
        $presenter = app(ActivityPresenter::class);

        return [
            TextColumn::make('created_at')
                ->label('Wanneer')
                ->dateTime()
                ->description(fn (Activity $record): string => $record->created_at->diffForHumans())
                ->sortable(),
            TextColumn::make('event')
                ->label('Gebeurtenis')
                ->badge()
                ->formatStateUsing(fn (Activity $record): string => $presenter->description($record))
                ->color(fn (Activity $record): string => $presenter->color($record))
                ->wrap(),
            TextColumn::make('member')
                ->label('Lid')
                ->state(fn (Activity $record): ?string => $presenter->memberLabel($record))
                ->url(fn (Activity $record): ?string => $presenter->memberUrl($record))
                ->placeholder('—'),
            TextColumn::make('causer')
                ->label('Door')
                ->state(fn (Activity $record): string => $presenter->causerName($record)),
        ];
    }
}
