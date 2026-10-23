<?php

namespace App\Filament\Resources\Renewals;

use App\Actions\Memberships\RenewMembership;
use App\Enums\MembershipStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Renewals\Pages\ListRenewals;
use App\Filament\Tables\ClubColumn;
use App\Models\Club;
use App\Models\Member;
use App\Models\Membership;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Annual renewals: who still has to be renewed for a year, and how far each renewal got.
 * The tabs and bulk actions live on ListRenewals; the work is done by RenewMembership and ActivateClubPaidMembership.
 */
class RenewalResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $slug = 'verlengingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $modelLabel = 'lid';

    protected static ?string $pluralModelLabel = 'leden';

    protected static ?string $navigationLabel = 'Verlengingen';

    protected static ?int $navigationSort = 15;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationBadge(): ?string
    {
        $due = Member::query()->dueForRenewal(RenewMembership::defaultYear(), RenewMembership::cutoff())->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Te verlengen voor '.RenewMembership::defaultYear().' (lidmaatschap loopt binnen '.RenewMembership::windowDays().' dagen af)';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, ListRenewals $livewire) => $query->with([
                'club',
                'currentMembership',
                'memberships' => fn ($memberships) => $memberships
                    ->whereDate('starts_at', '>=', $livewire->renewalYear().'-01-01')
                    ->whereDate('starts_at', '<=', $livewire->renewalYear().'-12-31')
                    ->where('status', '!=', MembershipStatus::Cancelled)
                    ->with('payment'),
            ]))
            ->columns([
                TextColumn::make('member_number')
                    ->label('Lidnummer')
                    ->formatStateUsing(fn (Member $record): string => $record->display_number)
                    ->fontFamily('mono')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Naam')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                ClubColumn::make(),
                TextColumn::make('email')
                    ->label('E-mailadres')
                    ->placeholder('Geen e-mailadres')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('currentMembership.ends_at')
                    ->label('Geldig t/m')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('renew_early_for')
                    ->label('Vervroegd')
                    ->state(fn (Member $record, ListRenewals $livewire): ?string => $record->renew_early_for === $livewire->renewalYear() ? 'Met de hand naar Te verlengen' : null)
                    ->badge()
                    ->color('gray')
                    ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'due'),
                TextColumn::make('renewal')
                    ->label(fn (ListRenewals $livewire): string => 'Verlenging '.$livewire->renewalYear())
                    ->badge()
                    ->state(fn (Member $record): string => self::renewalState($record))
                    ->color(fn (Member $record): string => self::renewalColor($record)),
            ])
            ->defaultSort('last_name')
            ->searchPlaceholder('Zoek op naam, e-mail of lidnummer')
            ->filters([
                SelectFilter::make('club')
                    ->label('Vereniging')
                    ->multiple()
                    ->options(fn (): array => ['none' => 'Geen'] + Club::orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? [];

                        if ($values === []) {
                            return $query;
                        }

                        return $query->where(fn (Builder $query) => $query
                            ->whereIn('club_id', array_filter($values, fn ($value) => $value !== 'none'))
                            ->when(in_array('none', $values, true), fn (Builder $query) => $query->orWhereNull('club_id')));
                    }),
            ])
            ->toolbarActions(RenewalActions::all())
            ->recordUrl(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->emptyStateHeading('Niemand in deze lijst')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    /**
     * What the renewal for the chosen year looks like for this member (or how it will go, when not renewed yet).
     */
    private static function renewalState(Member $record): string
    {
        $membership = $record->memberships->first();
        $club = $record->club?->name;

        if (! $membership instanceof Membership) {
            return match (true) {
                $record->paysCollectively() => "Geen betaalverzoek: betaald via {$club}",
                blank($record->email) => 'Betaalverzoek zonder e-mail (geen e-mailadres)',
                default => 'Betaalverzoek met betaallink per e-mail',
            };
        }

        return match (true) {
            $membership->isActive() => 'Actief',
            $membership->payment === null => 'Wacht op betaling door '.($club ?? 'de vereniging'),
            default => 'Wacht op betaling · '.Money::format($membership->payment->amount_cents),
        };
    }

    private static function renewalColor(Member $record): string
    {
        $membership = $record->memberships->first();

        return match (true) {
            ! $membership instanceof Membership => $record->paysCollectively() ? 'info' : (blank($record->email) ? 'warning' : 'gray'),
            $membership->isActive() => 'success',
            $membership->payment === null => 'info',
            default => 'warning',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRenewals::route('/'),
        ];
    }
}
