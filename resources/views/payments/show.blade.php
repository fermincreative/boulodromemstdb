@extends('layouts.public')

@section('title', __('payment.title'))
@section('subtitle', __('payment.subtitle'))

@push('head')
    <style>
        dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1.25rem; margin: 0 0 1rem; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; word-break: break-word; }
        .amount { font-size: 1.6rem; font-weight: 700; margin: 0 0 .25rem; }
        button { font: inherit; font-weight: 700; background: #000; color: var(--accent); border: 0; border-radius: .6rem; padding: .85rem 1.5rem; cursor: pointer; width: 100%; }
        button:hover { background: #222; }
        .alert { border-radius: .6rem; padding: .75rem 1rem; margin-bottom: 1rem; }
        .alert-error { background: #fee2e2; color: var(--error); }
        .alert-info { background: #faf6ee; border: 1px solid var(--accent); }
        .ok { color: #15803d; }
    </style>
@endpush

@section('content')
    @if ($state === 'paid')
        <div class="card">
            <h2 class="ok">{{ __('payment.paid_title') }}</h2>
            <p>{!! __('payment.paid', ['name' => e($member->first_name), 'amount' => e(\App\Support\Money::format($payment->amount_cents)), 'description' => e($payment->description)]) !!}</p>
            <p class="muted">{{ $member->email ? __('payment.paid_follow_up_to', ['email' => $member->email]) : __('payment.paid_follow_up') }}</p>
        </div>
    @elseif ($state === 'closed')
        <div class="card">
            <h2>{{ __('payment.closed_title') }}</h2>
            <p>{{ __('payment.closed', ['association' => config('membership_card.association_name')]) }}</p>
        </div>
    @else
        <div class="card">
            @if ($state === 'processing')
                <div class="alert alert-info" role="status">{{ __('payment.processing') }}</div>
            @elseif ($state === 'failed')
                <div class="alert alert-error" role="alert">{{ __('payment.failed') }}</div>
            @endif
            @if ($error)
                <div class="alert alert-error" role="alert">{{ $error }}</div>
            @endif

            <h2>{{ __('payment.title') }}</h2>
            <p class="amount">{{ \App\Support\Money::format($payment->amount_cents) }}</p>
            <p class="muted">{{ $payment->description }} — {{ $member->full_name }} ({{ $member->display_number }})</p>

            @if ($canPayOnline && $state !== 'processing')
                <form method="post" action="{{ $startUrl }}">
                    @csrf
                    <button type="submit">{{ __('payment.pay_online', ['methods' => $onlineMethods]) }}</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h2>{{ $canPayOnline ? __('payment.or_transfer') : __('payment.transfer') }}</h2>
            <dl>
                <dt>{{ __('payment.iban') }}</dt><dd>{{ $iban }}</dd>
                <dt>{{ __('payment.holder') }}</dt><dd>{{ $holder }}</dd>
                <dt>{{ __('payment.amount') }}</dt><dd>{{ \App\Support\Money::format($payment->amount_cents) }}</dd>
                <dt>{{ __('payment.reference') }}</dt><dd>{{ $reference }}</dd>
            </dl>
            <p class="muted">{{ __('payment.card_follows') }}</p>
        </div>
    @endif
@endsection
