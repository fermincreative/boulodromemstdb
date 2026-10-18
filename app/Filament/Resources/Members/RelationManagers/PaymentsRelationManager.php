<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Betalingen';

    protected static ?string $modelLabel = 'betaling';

    protected static ?string $pluralModelLabel = 'betalingen';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return PaymentResource::basePaymentTable($table)
            ->columns(PaymentResource::columns())
            ->filters([])
            ->emptyStateHeading('Nog geen betalingen')
            ->emptyStateDescription(null);
    }
}
