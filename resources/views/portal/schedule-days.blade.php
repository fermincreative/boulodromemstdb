{{-- Days of the Rooster as members see them (overview and full Rooster). Expects $days: [['date' => …, 'slots' => …], …]. --}}
@once
    <style>
        .day { display: grid; grid-template-columns: 6.5rem 1fr; gap: .25rem 1rem; padding: .75rem 0; border-top: 1px solid var(--line); }
        .day:first-of-type { border-top: 0; }
        .day-name { font-weight: 700; text-transform: capitalize; }
        .day-date { display: block; font-weight: 400; color: var(--muted); font-size: .85rem; }
        .day.is-today .day-name { color: var(--accent-dark); }
        .day.is-past { opacity: .55; }
        .slots { display: flex; flex-direction: column; gap: .4rem; }
        .slot { border-radius: .5rem; padding: .45rem .65rem; font-size: .9rem; border: 1px solid; }
        .slot-head { display: flex; flex-wrap: wrap; justify-content: space-between; column-gap: .5rem; font-weight: 600; }
        .slot-time { font-weight: 400; white-space: nowrap; }
        .slot.open { background: #dcfce7; border-color: #bbf7d0; color: #166534; }
        .slot.event { background: #e0f2fe; border-color: #bae6fd; color: #075985; }
        .slot.ended { opacity: .6; }
        .closed { color: var(--muted); font-size: .9rem; padding: .45rem 0; }
        @media (max-width: 420px) { .day { grid-template-columns: 1fr; } }
    </style>
@endonce

@foreach ($days as $day)
    @php($date = $day['date'])
    <section @class(['day', 'is-today' => $date->isToday(), 'is-past' => $date->lt(today())]) aria-label="{{ $date->translatedFormat('l j F') }}">
        <div class="day-name">
            {{ $date->translatedFormat('l') }}{{ $date->isToday() ? ' ('.__('portal.schedule.today').')' : '' }}
            <span class="day-date">{{ $date->format('d-m-Y') }}</span>
        </div>
        <div class="slots">
            @forelse ($day['slots'] as $slot)
                @php($label = match ($slot->period) {
                    \App\Enums\SchedulePeriod::Afternoon => __('portal.schedule.afternoon'),
                    \App\Enums\SchedulePeriod::Evening => __('portal.schedule.evening'),
                    default => __('portal.schedule.extra'),
                })
                <div @class(['slot', 'event' => $slot->isEvent(), 'open' => ! $slot->isEvent(), 'ended' => \App\Actions\Schedule\SaveScheduleSlot::hasEnded($date, $slot->ends_at)])>
                    <div class="slot-head">
                        <span>{{ $slot->isEvent() ? $slot->publicEventName() : $label }}</span>
                        <span class="slot-time">{{ $slot->times() }}</span>
                    </div>
                    @if ($slot->isEvent())
                        @php($free = $slot->reservableLanes())
                        {{ $free === 0 ? __('portal.schedule.no_lanes_free') : ($free === 1 ? __('portal.schedule.lane_free') : __('portal.schedule.lanes_free', ['lanes' => $free])) }}
                    @else
                        {{ $slot->lanes === 1 ? __('portal.schedule.open_one') : __('portal.schedule.open', ['lanes' => $slot->lanes]) }}
                    @endif
                </div>
            @empty
                <div class="closed">{{ __('portal.schedule.closed') }}</div>
            @endforelse
        </div>
    </section>
@endforeach
