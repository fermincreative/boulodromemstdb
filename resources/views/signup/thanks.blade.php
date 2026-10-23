@extends('layouts.public')

@section('title', __('signup.thanks.title'))
@section('subtitle', __('signup.subtitle'))

@push('head')
    <style>
        dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1.25rem; margin: 0 0 1rem; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; word-break: break-word; }
        .button { display: block; text-align: center; font-weight: 700; background: #000; color: var(--accent); border-radius: .6rem; padding: .85rem 1.5rem; text-decoration: none; }
        .button:hover { background: #222; }
    </style>
@endpush

@section('content')
    <div class="card">
        @if ($signup)
            <h2>{{ __('signup.thanks.heading_named', ['name' => $signup['name']]) }}</h2>
            <p>{!! __('signup.thanks.number', ['number' => e($signup['number']), 'email' => e($signup['email'])]) !!}</p>
        @else
            <h2>{{ __('signup.thanks.heading') }}</h2>
            <p>{{ __('signup.thanks.mail_follows') }}</p>
        @endif
    </div>

    @if ($signup)
        <div class="card">
            <h2>{{ __('signup.thanks.pay_title') }}</h2>
            @if (! empty($signup['pay_url']))
                <p>{!! __('signup.thanks.amount', ['description' => e($signup['description']), 'amount' => e($signup['amount'])]) !!}</p>
                <p><a class="button" href="{{ $signup['pay_url'] }}">{{ __('signup.thanks.pay_online', ['methods' => $signup['online_methods'] ?? 'iDEAL']) }}</a></p>
                <p>{{ __('signup.thanks.or_transfer') }}</p>
            @else
                <p>{!! __('signup.thanks.amount_transfer', ['description' => e($signup['description']), 'amount' => e($signup['amount'])]) !!}</p>
            @endif
            <dl>
                <dt>{{ __('signup.thanks.iban') }}</dt><dd>{{ $signup['iban'] }}</dd>
                <dt>{{ __('signup.thanks.holder') }}</dt><dd>{{ $signup['holder'] }}</dd>
                <dt>{{ __('signup.thanks.amount_label') }}</dt><dd>{{ $signup['amount'] }}</dd>
                <dt>{{ __('signup.thanks.reference') }}</dt><dd>{{ $signup['reference'] }}</dd>
            </dl>
            <p class="muted">{{ __('signup.thanks.card_follows') }}</p>
        </div>
    @endif
@endsection
