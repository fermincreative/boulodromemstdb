<?php

namespace App\Filament\Resources\Renewals\Pages;

use App\Actions\Memberships\RenewMembership;
use App\Enums\MembershipStatus;
use App\Filament\Resources\Renewals\RenewalResource;
use App\Models\Club;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Review screen for the annual renewal: tick the members to renew (all by default via "select all"), then "Verlengen".
 */
class ListRenewals extends ListRecords
{
    protected static string $resource = RenewalResource::class;

    #[Url(as: 'jaar')]
    public ?int $year = null;

    /**
     * The year being renewed: this year or next year (default: next year from October on).
     */
    public function renewalYear(): int
    {
        $thisYear = today()->year;

        if (! in_array($this->year, [$thisYear, $thisYear + 1], true)) {
            $this->year = RenewMembership::defaultYear();
        }

        return $this->year;
    }

    public function getTitle(): string
    {
        return 'Verlengingen '.$this->renewalYear();
    }

    public function getSubheading(): ?string
    {
        $year = $this->renewalYear();
        $clubs = Club::collectiveNames();

        return 'Leden die in '.($year - 1)." actief waren en nog geen lidmaatschap voor {$year} hebben. "
            .'Onder "Te verlengen" staat wie binnen '.RenewMembership::windowDays().' dagen afloopt; de rest staat onder "Nog niet aan de beurt". '
            .'Vink aan wie u wilt verlengen en kies "Verlengen": wie zelf betaalt, krijgt een e-mail met een betaallink (iDEAL / Bancontact).'
            .($clubs ? " Leden van {$clubs} krijgen geen betaalverzoek: zij wachten op betaling door hun vereniging." : '');
    }

    public function getTabs(): array
    {
        return [
            'not_yet_due' => Tab::make('Nog niet aan de beurt')
                ->icon(Heroicon::OutlinedPauseCircle)
                ->badge(fn (): int => Member::query()->notYetDueForRenewal($this->renewalYear(), RenewMembership::cutoff())->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->notYetDueForRenewal($this->renewalYear(), RenewMembership::cutoff())),
            'due' => Tab::make('Te verlengen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->badge(fn (): int => Member::query()->dueForRenewal($this->renewalYear(), RenewMembership::cutoff())->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->dueForRenewal($this->renewalYear(), RenewMembership::cutoff())),
            'awaiting_payment' => Tab::make('Wacht op betaling')
                ->icon(Heroicon::OutlinedClock)
                ->badge(fn (): int => Member::query()->withMembershipIn($this->renewalYear(), MembershipStatus::Pending, withPayment: true)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->withMembershipIn($this->renewalYear(), MembershipStatus::Pending, withPayment: true)),
            'awaiting_club' => Tab::make('Wacht op vereniging')
                ->icon(Heroicon::OutlinedUserGroup)
                ->badge(fn (): int => Member::query()->withMembershipIn($this->renewalYear(), MembershipStatus::Pending, withPayment: false)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->withMembershipIn($this->renewalYear(), MembershipStatus::Pending, withPayment: false)),
            'active' => Tab::make('Actief')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->badge(fn (): int => Member::query()->withMembershipIn($this->renewalYear(), MembershipStatus::Active)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->withMembershipIn($this->renewalYear(), MembershipStatus::Active)),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'due';
    }

    protected function getHeaderActions(): array
    {
        $thisYear = today()->year;

        return [
            ActionGroup::make(collect([$thisYear, $thisYear + 1])
                ->map(fn (int $year) => Action::make("year{$year}")
                    ->label("Verlengingen {$year}")
                    ->action(function () use ($year) {
                        $this->year = $year;
                        $this->deselectAllTableRecords();
                        $this->resetPage();
                    }))
                ->all())
                ->label('Jaar: '.$this->renewalYear())
                ->icon(Heroicon::OutlinedCalendar)
                ->color('gray')
                ->button(),
        ];
    }
}
