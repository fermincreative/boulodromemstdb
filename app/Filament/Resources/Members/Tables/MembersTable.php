<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\PaymentStatus;
use App\Filament\Actions\CardActions;
use App\Filament\Resources\Members\Actions\CancelMemberAction;
use App\Filament\Resources\Members\Actions\ExportMembersAction;
use App\Filament\Resources\Members\Actions\ReinstateMemberAction;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Tables\ClubColumn;
use App\Models\Club;
use App\Models\Member;
use App\Support\MemberNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')
                    ->label('Lidnummer')
                    ->formatStateUsing(fn (Member $record): string => $record->display_number)
                    ->fontFamily('mono')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // "SBM-0261-91" (check digits must be right), or part of the plain number ("26").
                        if (($number = MemberNumber::parse($search)) !== null && ! ctype_digit(trim($search))) {
                            return $query->where('member_number', $number);
                        }

                        return ctype_digit(trim($search))
                            ? $query->where('member_number', 'like', '%'.trim($search).'%')
                            : $query->whereRaw('0 = 1');
                    }),
                TextColumn::make('full_name')
                    ->label('Naam')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->description(fn (Member $record): ?string => $record->contact_for
                        ? "Contactpersoon van {$record->contact_for}"
                        : null),
                ClubColumn::make(),
                TextColumn::make('email')
                    ->label('E-mailadres')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable()
                    // About 40 characters per line; long addresses continue on the next line (they have no spaces to break on).
                    ->wrap()
                    ->extraAttributes(['style' => 'max-width: 40ch; overflow-wrap: anywhere;']),
                TextColumn::make('phone_mobile')
                    ->label('Mobiel')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('phone_home')
                    ->label('Telefoon thuis')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('street')
                    ->label('Adres')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('postal_code')
                    ->label('Postcode')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Woonplaats')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('birthday')
                    ->label('Verjaardag')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('joined_at')
                    ->label('Lid sinds')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currentMembership.ends_at')
                    ->label('Geldig t/m')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (Member $record): ?string => $record->currentMembership?->ends_at->isPast() ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Member $record): string => match (true) {
                        $record->hasLeft() => 'Opgezegd per '.$record->cancelled_at->format('d-m-Y'),
                        $record->isCancelled() => 'Stopt per '.$record->cancelled_at->format('d-m-Y'),
                        default => 'Actief',
                    })
                    ->color(fn (Member $record): string => match (true) {
                        $record->hasLeft() => 'danger',
                        $record->isCancelled() => 'warning',
                        default => 'success',
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['club', 'currentMembership.member']))
            ->defaultSort('last_name')
            ->searchPlaceholder('Zoek op naam, e-mail of lidnummer')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Actief (ook opgezegd per later)',
                        'leaving' => 'Opgezegd, nog lid',
                        'awaiting_payment' => 'Wacht op betaling',
                        'cancelled' => 'Opgezegd en vertrokken',
                    ])
                    ->default('active')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active' => $query->current(),
                        'leaving' => $query->whereDate('cancelled_at', '>=', today()),
                        'cancelled' => $query->former(),
                        'awaiting_payment' => $query->current()
                            ->whereHas('payments', fn (Builder $payments) => $payments->where('status', PaymentStatus::Open)),
                        default => $query,
                    }),
                SelectFilter::make('club')
                    ->label('Vereniging')
                    ->multiple()
                    ->options(fn (): array => ['none' => 'Geen'] + Club::orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? [];

                        if ($values === []) {
                            return $query;
                        }

                        $clubIds = array_values(array_filter($values, fn ($value) => $value !== 'none'));

                        return $query->where(function (Builder $query) use ($values, $clubIds) {
                            $query->whereIn('club_id', $clubIds);

                            if (in_array('none', $values, true)) {
                                $query->orWhereNull('club_id');
                            }
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ...CardActions::all(),
                    EditAction::make(),
                    CancelMemberAction::make(),
                    ReinstateMemberAction::make(),
                ]),
            ])
            ->toolbarActions([
                ExportMembersAction::bulk(),
            ])
            ->recordUrl(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50);
    }
}
