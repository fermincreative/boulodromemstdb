<?php

namespace App\Filament\Pages;

use App\Actions\Schedule\EditScheduleSlot;
use App\Actions\Schedule\InvalidRepeat;
use App\Actions\Schedule\SaveScheduleSlot;
use App\Actions\Schedule\ScheduleSeries;
use App\Actions\Schedule\SlotSettings;
use App\Enums\SchedulePeriod;
use App\Enums\ScheduleSlotStatus;
use App\Filament\Forms\DateInput;
use App\Filament\Forms\TimeInput;
use App\Models\ScheduleSlot;
use App\Services\OpeningHours;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * The Rooster: per week, for each day whether the Boulodrôme is open in the afternoon and/or evening,
 * how many lanes are available, and events. Admins edit a part of a day by clicking it.
 */
class Schedule extends Page
{
    protected static ?string $slug = 'rooster';

    protected static ?string $title = 'Rooster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 25;

    /** Monday of the week shown (Y-m-d). */
    #[Url(as: 'week')]
    public ?string $week = null;

    public function weekStart(): CarbonImmutable
    {
        try {
            $date = $this->week ? CarbonImmutable::createFromFormat('!Y-m-d', $this->week) : null;
        } catch (Throwable) {
            $date = null;
        }

        return ($date ?: CarbonImmutable::today())->startOfWeek(CarbonInterface::MONDAY);
    }

    public function getTitle(): string
    {
        $start = $this->weekStart();
        $end = $start->addDays(6);

        return 'Rooster week '.$start->isoWeek().' ('.$start->translatedFormat($start->month === $end->month ? 'j' : 'j F')
            .' – '.$end->translatedFormat('j F Y').')';
    }

    public function getSubheading(): ?string
    {
        return 'Middag '.OpeningHours::format(...OpeningHours::times(SchedulePeriod::Afternoon))
            .', avond '.OpeningHours::format(...OpeningHours::times(SchedulePeriod::Evening)).'. '
            .(self::canManage() ? 'Klik op een dagdeel om het te wijzigen, of voeg een extra dagdeel met eigen tijden toe.' : '');
    }

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->can('manage', ScheduleSlot::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.schedule-week')->viewData(fn (): array => [
                'days' => $this->days(),
                'canManage' => self::canManage(),
            ]),
        ]);
    }

    /**
     * The seven days of the week, each with its parts of the day in time order: the open ones (middag, avond, extra)
     * and, for a closed middag/avond, a placeholder with the standard times (blocked when an extra part overlaps it).
     *
     * @return Collection<int, array{date: CarbonImmutable, canAdd: bool, entries: array<int, array{period: SchedulePeriod, slot: ScheduleSlot|null, startsAt: string, times: string, blockedBy: ScheduleSlot|null, past: bool, repeatsUntil: CarbonImmutable|null}>}>
     */
    public function days(): Collection
    {
        $start = $this->weekStart();
        $week = ScheduleSlot::between($start, $start->addDays(6))->orderBy('starts_at')->get();
        $slots = $week->groupBy(fn (ScheduleSlot $slot) => $slot->date->toDateString());
        // Last date of each repeated event in this week.
        $seriesEnds = ScheduleSlot::whereIn('series_id', $week->pluck('series_id')->filter()->unique())
            ->groupBy('series_id')
            ->selectRaw('series_id, max(date) as last_date')
            ->pluck('last_date', 'series_id');

        return collect(range(0, 6))->map(function (int $offset) use ($start, $slots, $seriesEnds) {
            $date = $start->addDays($offset);
            $daySlots = $slots->get($date->toDateString(), collect());

            $entries = $daySlots->map(fn (ScheduleSlot $slot) => [
                'period' => $slot->period,
                'slot' => $slot,
                'startsAt' => $slot->starts_at,
                'times' => $slot->times(),
                'blockedBy' => null,
                'past' => SaveScheduleSlot::hasEnded($date, $slot->ends_at),
                'repeatsUntil' => $slot->series_id && $seriesEnds->has($slot->series_id) ? CarbonImmutable::parse($seriesEnds[$slot->series_id]) : null,
            ]);

            foreach (SchedulePeriod::standard() as $period) {
                if ($daySlots->contains(fn (ScheduleSlot $slot) => $slot->period === $period)) {
                    continue;
                }

                [$startsAt, $endsAt] = OpeningHours::times($period);
                $entries->push([
                    'period' => $period,
                    'slot' => null,
                    'startsAt' => $startsAt,
                    'times' => OpeningHours::format($startsAt, $endsAt),
                    'blockedBy' => $daySlots->first(fn (ScheduleSlot $slot) => $slot->overlaps($startsAt, $endsAt)),
                    'past' => SaveScheduleSlot::hasEnded($date, $endsAt),
                    'repeatsUntil' => null,
                ]);
            }

            return [
                'date' => $date,
                'entries' => $entries->sortBy('startsAt')->values()->all(),
                // Extra parts of a day can be added from today on.
                'canAdd' => ! $date->lt(CarbonImmutable::today()),
            ];
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousWeek')
                ->label('Vorige week')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->color('gray')
                ->action(fn () => $this->week = $this->weekStart()->subWeek()->toDateString()),
            Action::make('thisWeek')
                ->label('Deze week')
                ->color('gray')
                ->disabled(fn (): bool => $this->weekStart()->isSameDay(CarbonImmutable::today()->startOfWeek(CarbonInterface::MONDAY)))
                ->action(fn () => $this->week = null),
            Action::make('nextWeek')
                ->label('Volgende week')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->week = $this->weekStart()->addWeek()->toDateString()),
            Action::make('goToDate')
                ->label('Ga naar datum')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->modalHeading('Ga naar de week van')
                ->modalWidth('sm')
                ->modalSubmitActionLabel('Tonen')
                ->fillForm(fn (): array => ['date' => $this->weekStart()->toDateString()])
                ->schema([
                    DatePicker::make('date')
                        ->hiddenLabel()
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->firstDayOfWeek(1)
                        ->closeOnDateSelection()
                        ->required(),
                ])
                ->action(fn (array $data) => $this->week = CarbonImmutable::parse($data['date'])->startOfWeek(CarbonInterface::MONDAY)->toDateString()),
        ];
    }

    /**
     * Opened from the week view with arguments: date, period, and for an existing extra part of the day its id.
     */
    public function editSlotAction(): Action
    {
        $max = SaveScheduleSlot::maxLanes();
        $isEvent = fn (Get $get): bool => self::status($get('status')) === ScheduleSlotStatus::Event;
        $isOpen = fn (Get $get): bool => in_array(self::status($get('status')), [ScheduleSlotStatus::Open, ScheduleSlotStatus::Event], true);

        return Action::make('editSlot')
            ->authorize(fn (): bool => self::canManage())
            ->modalHeading(function (array $arguments): string {
                $period = self::period($arguments);
                $slot = $this->slotFor($arguments);
                $times = match (true) {
                    $slot !== null => ' ('.$slot->times().')',
                    $period->isStandard() => ' ('.OpeningHours::format(...OpeningHours::times($period)).')',
                    default => ' toevoegen',
                };

                return ucfirst(self::date($arguments)->translatedFormat('l j F Y')).' — '.$period->getLabel().$times;
            })
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Opslaan')
            ->fillForm(function (array $arguments) use ($max): array {
                $period = self::period($arguments);
                $slot = $this->slotFor($arguments);
                [$startsAt, $endsAt] = $slot ? [$slot->starts_at, $slot->ends_at] : ($period->isStandard() ? OpeningHours::times($period) : [null, null]);

                return [
                    // Clicking a closed part of the day is almost always to open it.
                    'status' => ($slot?->status() ?? ScheduleSlotStatus::Open)->value,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'lanes' => $slot?->lanes ?? $max,
                    'event_name' => $slot?->event_name,
                    'event_name_public' => (bool) $slot?->event_name_public,
                    // An event takes all lanes and blocks the rest, unless the admin changes it.
                    'event_lanes' => $slot?->event_lanes ?? $slot?->lanes ?? $max,
                    'remaining_lanes_reservable' => $slot?->isEvent() ? $slot->remaining_lanes_reservable : false,
                    'repeat' => false,
                    'repeat_every' => 1,
                    'repeat_until' => null,
                    'apply_to_following' => false,
                ];
            })
            ->schema(fn (array $arguments): array => [
                Radio::make('status')
                    ->label('Status')
                    ->options(self::statusOptions(self::period($arguments), $this->slotFor($arguments)))
                    ->inline()
                    ->required()
                    ->live(),
                Grid::make(2)
                    ->visible(fn (Get $get): bool => self::period($arguments) === SchedulePeriod::Custom && $isOpen($get))
                    ->schema([
                        TimeInput::make('starts_at')->label('Van')->required(),
                        TimeInput::make('ends_at')->label('Tot')->required(),
                    ]),
                TextInput::make('lanes')
                    ->label('Beschikbare banen')
                    ->helperText("Maximaal {$max}.")
                    ->integer()
                    ->minValue(1)
                    ->maxValue($max)
                    ->required()
                    ->live(onBlur: true)
                    // The event can never have more lanes than are open.
                    ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => (int) $get('event_lanes') > (int) $state ? $set('event_lanes', (int) $state) : null)
                    ->visible($isOpen),
                TextInput::make('event_name')
                    ->label('Evenement')
                    ->placeholder('Bijv. clubkampioenschap, besloten feest')
                    ->maxLength(255)
                    ->required()
                    ->visible($isEvent),
                Toggle::make('event_name_public')
                    ->label('Naam van het evenement is openbaar')
                    ->helperText('Uit: leden en bezoekers zien alleen "Evenement". In dit rooster ziet u de naam altijd.')
                    ->visible($isEvent),
                TextInput::make('event_lanes')
                    ->label('Banen voor het evenement')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (Get $get): int => min($max, max(1, (int) $get('lanes'))))
                    ->required()
                    ->live(onBlur: true)
                    ->visible($isEvent),
                Toggle::make('remaining_lanes_reservable')
                    ->label('Overige banen zijn te reserveren')
                    ->helperText(fn (Get $get): string => $get('remaining_lanes_reservable')
                        ? 'Vrij voor reservering: '.max(0, (int) $get('lanes') - (int) $get('event_lanes')).' banen.'
                        : 'Uit: er kan niets gereserveerd worden tijdens het evenement.')
                    ->live()
                    ->visible($isEvent),
                ...$this->repeatFields($arguments, $isEvent),
            ])
            ->action(function (array $data, array $arguments): void {
                $period = self::period($arguments);
                $repeat = self::status($data['status']) === ScheduleSlotStatus::Event && ($data['repeat'] ?? false);

                try {
                    $result = app(EditScheduleSlot::class)->handle(
                        self::date($arguments),
                        $period,
                        new SlotSettings(
                            status: self::status($data['status']) ?? ScheduleSlotStatus::Closed,
                            lanes: isset($data['lanes']) ? (int) $data['lanes'] : null,
                            eventName: $data['event_name'] ?? null,
                            eventNamePublic: (bool) ($data['event_name_public'] ?? false),
                            eventLanes: isset($data['event_lanes']) ? (int) $data['event_lanes'] : null,
                            remainingLanesReservable: (bool) ($data['remaining_lanes_reservable'] ?? false),
                            startsAt: $data['starts_at'] ?? null,
                            endsAt: $data['ends_at'] ?? null,
                        ),
                        $this->slotFor($arguments),
                        repeatUntil: $repeat && filled($data['repeat_until'] ?? null) ? CarbonImmutable::parse($data['repeat_until']) : null,
                        repeatEveryWeeks: (int) ($data['repeat_every'] ?? 1),
                        applyToFollowing: (bool) ($data['apply_to_following'] ?? false),
                    );
                } catch (InvalidArgumentException $e) {
                    $field = match (true) {
                        $e instanceof InvalidRepeat => 'repeat_until',
                        $period->isStandard() => 'status',
                        default => 'starts_at',
                    };

                    throw ValidationException::withMessages(["mountedActions.0.data.{$field}" => $e->getMessage()]);
                }

                Notification::make()
                    ->title('Rooster bijgewerkt')
                    ->body(self::seriesSummary($result))
                    ->success()
                    ->send();
            });
    }

    /**
     * Repeating a new event, or carrying a change over to the later dates of a repeated one.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, mixed>
     */
    private function repeatFields(array $arguments, Closure $isEvent): array
    {
        $date = self::date($arguments);
        $slot = $this->slotFor($arguments);

        if ($slot?->isRepeated()) {
            $following = $slot->followingInSeries()->count();
            $last = ScheduleSeries::lastDate($slot);

            return [
                Toggle::make('apply_to_following')
                    ->label("Ook toepassen op de volgende herhalingen ({$following}, t/m {$last?->format('d-m-Y')})")
                    ->helperText('Uit: alleen deze datum verandert. Kies "'.($slot->period->isStandard() ? 'Gesloten' : 'Verwijderen').'" en zet dit aan om de rest van de reeks te schrappen.')
                    ->visible($following > 0),
            ];
        }

        $isRepeating = fn (Get $get): bool => $isEvent($get) && (bool) $get('repeat');

        return [
            Toggle::make('repeat')
                ->label('Herhalen')
                ->live()
                ->visible($isEvent),
            Grid::make(2)
                ->visible($isRepeating)
                ->schema([
                    Select::make('repeat_every')
                        ->label('Hoe vaak')
                        ->options([1 => 'Elke '.$date->translatedFormat('l'), 2 => 'Om de week op '.$date->translatedFormat('l')])
                        ->native(false)
                        ->required(),
                    DateInput::make('repeat_until', min: $date->addWeek(), max: $date->addMonths(ScheduleSeries::MAX_MONTHS))
                        ->label('Tot en met')
                        ->required(),
                ]),
        ];
    }

    /**
     * "Herhaald: 11 keer. Overgeslagen: 07-11-2026 (er staat al een evenement (Competitie))."
     *
     * @param  array{repeated: int, changed: int, skipped: array<string, string>}  $result
     */
    private static function seriesSummary(array $result): ?string
    {
        $skipped = collect($result['skipped'])->map(fn (string $reason, string $date) => "{$date} ({$reason})");

        return collect([
            $result['repeated'] ? "Herhaald: {$result['repeated']} keer." : null,
            $result['changed'] ? "Volgende herhalingen bijgewerkt: {$result['changed']}." : null,
            $skipped->isNotEmpty() ? 'Overgeslagen: '.$skipped->take(5)->implode(', ').($skipped->count() > 5 ? ' en '.($skipped->count() - 5).' meer' : '').'.' : null,
        ])->filter()->implode(' ') ?: null;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(SchedulePeriod $period, ?ScheduleSlot $slot): array
    {
        $options = [
            ScheduleSlotStatus::Open->value => ScheduleSlotStatus::Open->getLabel(),
            ScheduleSlotStatus::Event->value => ScheduleSlotStatus::Event->getLabel(),
        ];

        return match (true) {
            $period->isStandard() => [ScheduleSlotStatus::Closed->value => ScheduleSlotStatus::Closed->getLabel()] + $options,
            $slot !== null => $options + [ScheduleSlotStatus::Closed->value => 'Verwijderen'],
            default => $options,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function period(array $arguments): SchedulePeriod
    {
        return SchedulePeriod::tryFrom((string) ($arguments['period'] ?? '')) ?? SchedulePeriod::Afternoon;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function date(array $arguments): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', (string) ($arguments['date'] ?? '')) ?: CarbonImmutable::today();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }

    /**
     * The status field holds the enum once Filament has cast it, or its value before that.
     */
    private static function status(mixed $state): ?ScheduleSlotStatus
    {
        return $state instanceof ScheduleSlotStatus ? $state : ScheduleSlotStatus::tryFrom((string) $state);
    }

    /**
     * The part of the day the action is about: an extra one by id (on that date), middag/avond by date. Null when not open yet.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function slotFor(array $arguments): ?ScheduleSlot
    {
        $period = self::period($arguments);
        $query = ScheduleSlot::whereDate('date', self::date($arguments))->where('period', $period);

        if ($period->isStandard()) {
            return $query->first();
        }

        return isset($arguments['id']) ? $query->whereKey($arguments['id'])->first() : null;
    }
}
