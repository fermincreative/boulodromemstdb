<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Actions\CardActions;
use App\Filament\Resources\Members\Actions\CancelMemberAction;
use App\Filament\Resources\Members\Actions\ReinstateMemberAction;
use App\Filament\Resources\Members\Actions\SendWelcomeMailAction;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Right after creating a member: open the welcome e-mail.
        if (request()->boolean('welcome')) {
            $this->defaultAction = 'sendWelcome';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(CardActions::all())
                ->label('Kaart')
                ->icon(Heroicon::OutlinedIdentification)
                ->button()
                ->color('gray'),
            SendWelcomeMailAction::make(),
            EditAction::make(),
            CancelMemberAction::make(),
            ReinstateMemberAction::make(),
        ];
    }
}
