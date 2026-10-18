<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'betalingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'betaling';

    protected static ?string $pluralModelLabel = 'betalingen';

    protected static ?string $navigationLabel = 'Betalingen';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $open = Payment::where('status', PaymentStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Openstaande betalingen';
    }

    public static function table(Table $table): Table
    {
        return self::basePaymentTable($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('member'))
            ->columns([
                TextColumn::make('member.last_name')
                    ->label('Lid')
                    ->formatStateUsing(fn (Payment $record): string => "{$record->member->full_name} ({$record->member->display_number})")
                    ->url(fn (Payment $record): string => MemberResource::getUrl('view', ['record' => $record->member]))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                ...self::columns(),
            ]);
    }

    /**
     * Columns shared with the Betalingen tab on a member.
     *
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('description')
                ->label('Omschrijving')
                ->wrap(),
            TextColumn::make('amount_cents')
                ->label('Bedrag')
                ->formatStateUsing(fn (int $state): string => Money::format($state))
                ->alignEnd()
                ->sortable(),
            TextColumn::make('status')
                ->label('Status')
                ->badge(),
            TextColumn::make('created_at')
                ->label('Aangemaakt')
                ->date()
                ->sortable(),
            TextColumn::make('paid_at')
                ->label('Betaald op')
                ->date()
                ->description(fn (Payment $record): ?string => $record->method?->getLabel())
                ->placeholder('—')
                ->sortable(),
        ];
    }

    public static function basePaymentTable(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::Open->value),
                SelectFilter::make('method')
                    ->label('Betaalwijze')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                PaymentActions::markPaid(),
                ActionGroup::make([
                    PaymentActions::cancel(),
                ]),
            ])
            ->toolbarActions([
                PaymentActions::bulkMarkPaid(),
            ])
            ->emptyStateHeading('Geen betalingen gevonden')
            ->emptyStateDescription('Pas de filters aan om ook betaalde of geannuleerde betalingen te zien.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
