<?php

namespace App\Filament\Resources\Members\Pages;

use App\Actions\Members\CreateMember as CreateMemberAction;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Members\Actions\SendWelcomeMailAction;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // The membership fields are not dehydrated into $data; read them from the raw form state.
        $state = $this->form->getRawState();
        $withMembership = (bool) ($state['add_membership'] ?? false);
        $paidWith = $withMembership ? PaymentMethod::tryFrom((string) ($state['payment'] ?? '')) : null;

        return app(CreateMemberAction::class)->handle($data, $withMembership, $paidWith, notify: (bool) ($state['notify_paid'] ?? true));
    }

    /**
     * With an open payment and an e-mail address, the member page opens with the (editable) welcome e-mail.
     */
    protected function getRedirectUrl(): string
    {
        $member = $this->getRecord();
        $parameters = ['record' => $member];

        if (filled($member->email) && SendWelcomeMailAction::openPayment($member)) {
            $parameters['welcome'] = 1;
        }

        return $this->getResource()::getUrl('view', $parameters);
    }
}
