<?php

namespace App\Filament\Resources\Members\Actions;

use App\Actions\Members\ReinstateMember;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ReinstateMemberAction
{
    public static function make(): Action
    {
        return Action::make('reinstate')
            ->label('Opzegging intrekken')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (Member $record): string => "Opzegging van {$record->full_name} intrekken?")
            ->authorize('reinstate')
            ->action(function (Member $record): void {
                app(ReinstateMember::class)->handle($record);

                Notification::make()->title('Opzegging ingetrokken')->success()->send();
            });
    }
}
