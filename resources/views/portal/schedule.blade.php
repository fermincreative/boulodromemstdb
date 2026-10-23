@extends('layouts.public')

@section('title', __('portal.schedule.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
    <style>
        .week-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: .25rem 1rem; margin-bottom: .25rem; }
        .week-head h2 { margin: 0; }
        .week-range { color: var(--muted); font-size: .9rem; }
        .week-nav { display: flex; justify-content: space-between; gap: .5rem; margin: 1rem 0 0; }
        .week-nav a { font-size: .9rem; }
    </style>
@endpush

@section('content')
    @include('portal.topbar')

    <div class="card">
        <div class="week-head">
            <h2>{{ __('portal.schedule.week', ['number' => $start->isoWeek()]) }}</h2>
            <span class="week-range">{{ __('portal.schedule.range', ['from' => $start->translatedFormat('j F'), 'until' => $start->addDays(6)->translatedFormat('j F Y')]) }}</span>
        </div>
        <p class="muted">{{ __('portal.schedule.intro') }}</p>

        @include('portal.schedule-days', ['days' => $days])

        <div class="week-nav">
            <span>
                @if ($previous)
                    <a href="{{ route('portal.schedule', ['week' => $previous->toDateString()]) }}">← {{ __('portal.schedule.previous') }}</a>
                    @unless ($previous->isSameDay(today()->startOfWeek(\Carbon\CarbonInterface::MONDAY)))
                        · <a href="{{ route('portal.schedule') }}">{{ __('portal.schedule.this_week') }}</a>
                    @endunless
                @endif
            </span>
            <a href="{{ route('portal.schedule', ['week' => $next->toDateString()]) }}">{{ __('portal.schedule.next') }} →</a>
        </div>
    </div>
@endsection
