@php
    use Illuminate\Support\Js;
@endphp

{{-- Columns follow the width the Rooster gets (next to the sidebar), not the window: 7 days only when they fit. --}}
<div class="rooster-wrap">
    <style>
        .rooster-wrap { container-type: inline-size; }
        .rooster { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); }
        @container (min-width: 30rem) { .rooster { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @container (min-width: 46rem) { .rooster { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @container (min-width: 70rem) { .rooster { grid-template-columns: repeat(7, minmax(0, 1fr)); } }
        .rooster-day { min-width: 0; display: flex; flex-direction: column; gap: .5rem; border-radius: .75rem; padding: .75rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid var(--gray-200); }
        .dark .rooster-day { background: var(--gray-900); outline-color: rgb(255 255 255 / .1); }
        .rooster-day.is-today { outline: 2px solid var(--primary-500); }
        .rooster-day.is-past { opacity: .65; }
        .rooster-day-name { font-weight: 600; font-size: .9rem; text-transform: capitalize; }
        .rooster-day-date { font-size: .8rem; color: var(--gray-500); }
        .rooster-slot { display: block; width: 100%; min-width: 0; text-align: left; border-radius: .5rem; padding: .5rem .6rem; font-size: .8rem; line-height: 1.35; border: 1px solid; }
        button.rooster-slot { cursor: pointer; }
        button.rooster-slot:hover { filter: brightness(.97); }
        button.rooster-slot:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
        .rooster-slot-head { display: flex; flex-wrap: wrap; justify-content: space-between; column-gap: .4rem; font-weight: 600; }
        .rooster-slot-time { font-weight: 400; color: var(--gray-500); white-space: nowrap; }
        .rooster-slot-body { overflow-wrap: anywhere; }
        .rooster-slot-body { margin-top: .2rem; }
        .rooster-slot-event { font-weight: 600; overflow-wrap: anywhere; }
        .rooster-slot-visibility { font-size: .7rem; opacity: .8; }
        .rooster-closed { background: var(--gray-50); border-color: var(--gray-200); color: var(--gray-500); }
        .rooster-open { background: var(--success-50); border-color: var(--success-200); color: var(--success-800); }
        .rooster-event { background: var(--info-50); border-color: var(--info-200); color: var(--info-800); }
        .rooster-blocked { border-style: dashed; font-style: italic; }
        .rooster-add { margin-top: auto; width: 100%; border: 1px dashed var(--gray-300); border-radius: .5rem; padding: .35rem; font-size: .75rem; color: var(--gray-500); background: transparent; cursor: pointer; }
        .rooster-add:hover { color: var(--primary-600); border-color: var(--primary-400); }
        .dark .rooster-add { border-color: rgb(255 255 255 / .15); color: var(--gray-400); }
        .dark .rooster-closed { background: rgb(255 255 255 / .03); border-color: rgb(255 255 255 / .1); color: var(--gray-400); }
        .dark .rooster-open { background: color-mix(in oklab, var(--success-500) 12%, transparent); border-color: color-mix(in oklab, var(--success-500) 35%, transparent); color: var(--success-300); }
        .dark .rooster-event { background: color-mix(in oklab, var(--info-500) 12%, transparent); border-color: color-mix(in oklab, var(--info-500) 35%, transparent); color: var(--info-300); }
    </style>

    <div class="rooster">

    @foreach ($days as $day)
        @php($date = $day['date'])
        <section @class(['rooster-day', 'is-today' => $date->isToday(), 'is-past' => $date->isPast() && ! $date->isToday()])
                 aria-label="{{ $date->translatedFormat('l j F Y') }}">
            <div>
                <div class="rooster-day-name">{{ $date->translatedFormat('l') }}{{ $date->isToday() ? ' (vandaag)' : '' }}</div>
                <div class="rooster-day-date">{{ $date->format('d-m-Y') }}</div>
            </div>

            @foreach ($day['entries'] as $entry)
                @php($slot = $entry['slot'])
                @php($blocked = $entry['blockedBy'])
                @php($clickable = $canManage && ! $blocked && ! $entry['past'])
                @php($tag = $clickable ? 'button' : 'div')
                <{{ $tag }}
                    @if ($clickable)
                        type="button"
                        wire:click="mountAction('editSlot', {{ Js::from(array_filter(['date' => $date->toDateString(), 'period' => $entry['period']->value, 'id' => $slot?->getKey()])) }})"
                    @endif
                    @class([
                        'rooster-slot',
                        'rooster-closed' => ! $slot,
                        'rooster-blocked' => $blocked,
                        'rooster-open' => $slot && ! $slot->isEvent(),
                        'rooster-event' => $slot?->isEvent(),
                    ])
                >
                    <span class="rooster-slot-head">
                        <span>{{ $entry['period']->isStandard() ? $entry['period']->getLabel() : 'Extra' }}</span>
                        <span class="rooster-slot-time">{{ $entry['times'] }}</span>
                    </span>
                    <span class="rooster-slot-body" style="display: block">
                        @if ($blocked)
                            Niet mogelijk: overlapt met {{ $blocked->times() }}
                        @elseif (! $slot)
                            Gesloten
                        @elseif (! $slot->isEvent())
                            Open · {{ $slot->lanes }} {{ $slot->lanes === 1 ? 'baan' : 'banen' }}
                        @else
                            <span class="rooster-slot-event" style="display: block">{{ $slot->event_name }}</span>
                            <span class="rooster-slot-visibility" style="display: block">{{ $slot->event_name_public ? 'Naam openbaar' : 'Naam niet openbaar' }}</span>
                            @if ($entry['repeatsUntil'])
                                <span class="rooster-slot-visibility" style="display: block">↻ Herhaalt t/m {{ $entry['repeatsUntil']->format('d-m-Y') }}</span>
                            @endif
                            {{ $slot->event_lanes === $slot->lanes ? 'Alle '.$slot->lanes.' banen' : $slot->event_lanes.' van '.$slot->lanes.' banen' }} · {{ $slot->reservableLanes() > 0 ? $slot->reservableLanes().' te reserveren' : 'niets te reserveren' }}
                        @endif
                    </span>
                </{{ $tag }}>
            @endforeach

            @if ($canManage && $day['canAdd'])
                <button type="button" class="rooster-add"
                        wire:click="mountAction('editSlot', {{ Js::from(['date' => $date->toDateString(), 'period' => 'custom']) }})">
                    + Extra dagdeel
                </button>
            @endif
        </section>
    @endforeach
    </div>
</div>
