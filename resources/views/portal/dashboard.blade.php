@extends('layouts.public')

@section('title', __('portal.dashboard.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
    <style>
        .member-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .25rem 1rem; align-items: baseline; margin-bottom: .75rem; }
        .member-head h2 { margin: 0; }
        .number { font-family: ui-monospace, monospace; color: var(--muted); font-size: .9rem; }
        .status { border-radius: .6rem; padding: .6rem .85rem; margin-bottom: .75rem; }
        .status-ok { background: #dcfce7; color: #166534; }
        .status-wait { background: #faf6ee; border: 1px solid var(--accent); }
        .status-off { background: #f4f4f5; color: var(--muted); }
        h3 { font-size: .95rem; margin: 1.25rem 0 .5rem; }
        .section-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin: 1.25rem 0 .5rem; }
        .section-head h3 { margin: 0; }
        .section-head a { font-size: .9rem; }
        .buttons { display: flex; flex-wrap: wrap; gap: .5rem; }
        .payment { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; padding: .6rem 0; border-top: 1px solid var(--line); }
        dl { display: grid; grid-template-columns: auto 1fr; gap: .3rem 1rem; margin: 0; font-size: .9rem; }
        dt { color: var(--muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        .history { list-style: none; padding: 0; margin: 0; font-size: .9rem; }
        .history li { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem 0; border-top: 1px solid var(--line); }
        /* Only the QR code: what is shown at the entrance. */
        .qr { text-align: center; margin: 1rem 0 .25rem; }
        .qr svg { display: block; width: 100%; max-width: 15rem; height: auto; margin: 0 auto; border-radius: .5rem; }
        .qr-hint { font-weight: 600; margin-top: .5rem; }
        .qr-line { color: var(--muted); font-size: .85rem; }
        .schedule-more { display: block; text-align: center; margin-top: 1rem; }
        .downloads { text-align: center; }
    </style>
@endpush

@section('content')
    @include('portal.topbar')

    {{-- Status messages are built with escaped values in the controller. --}}
    @if (session('status'))
        <div class="alert alert-info" role="status">{!! session('status') !!}</div>
    @endif
    @error('card')
        <div class="alert alert-error" role="alert">{{ $message }}</div>
    @enderror

    @foreach ($members as $entry)
        @php($member = $entry['member'])

        <div class="card">
            <div class="member-head">
                <h2>{{ $member->full_name }}</h2>
                <span class="number">{{ $member->display_number }}{{ $member->club ? ' · '.$member->club->name : '' }}</span>
            </div>

            @if ($member->hasLeft())
                <div class="status status-off">{{ __('portal.dashboard.ended', ['date' => $member->cancelled_at->format('d-m-Y')]) }}</div>
            @elseif ($entry['validToday'])
                <div class="status status-ok">{!! __('portal.dashboard.member_until', ['date' => $entry['paidUntil']->format('d-m-Y')]) !!}</div>
            @else
                <div class="status status-off">{{ __('portal.dashboard.not_valid') }}</div>
            @endif

            @if ($entry['pending'] && ! $member->hasLeft())
                <div class="status status-wait">
                    {{ $entry['pending']->payment_id
                        ? __('portal.dashboard.pending_payment', ['year' => $entry['pending']->cardYear()])
                        : __('portal.dashboard.pending_club', ['year' => $entry['pending']->cardYear(), 'club' => $member->club?->name ?? __('portal.dashboard.your_club')]) }}
                </div>
            @endif

            @if ($member->payments->isNotEmpty())
                <h3>{{ __('portal.dashboard.to_pay') }}</h3>
                @foreach ($member->payments as $payment)
                    <div class="payment">
                        <span>{{ $payment->description }}: <strong>{{ \App\Support\Money::format($payment->amount_cents) }}</strong></span>
                        <a class="button" href="{{ \App\Support\PaymentLink::for($payment) }}">{{ __('portal.dashboard.pay') }}</a>
                    </div>
                @endforeach
            @endif

            @if ($entry['card'])
                @php($card = $entry['card'])
                <div class="qr">
                    @if ($entry['qr'])
                        <div aria-label="{{ __('portal.dashboard.card_label', ['year' => $card->cardYear()]) }}">{!! $entry['qr'] !!}</div>
                        <div class="qr-hint">{{ __('portal.dashboard.show_at_entrance') }}</div>
                    @endif
                    <div class="qr-line">{{ __('portal.dashboard.card_line', ['year' => $card->cardYear(), 'date' => $card->ends_at->format('d-m-Y')]) }}</div>
                </div>
                <p class="downloads muted">
                    {{ __('portal.dashboard.as_file') }}
                    <a href="{{ route('portal.card', [$card, \App\Services\MembershipCard::PHONE]) }}">{{ __('portal.dashboard.download_phone') }}</a> ·
                    <a href="{{ route('portal.card', [$card, \App\Services\MembershipCard::PRINT]) }}">{{ __('portal.dashboard.download_print') }}</a>
                </p>
                @if ($entry['nextPaid'])
                    <p class="muted">{{ __('portal.dashboard.next_paid', ['year' => $entry['nextPaid']->cardYear(), 'date' => $entry['nextPaid']->starts_at->format('d-m-Y')]) }}</p>
                @endif
            @endif
        </div>
    @endforeach

    <div class="card">
        <h2>{{ __('portal.dashboard.upcoming') }}</h2>
        @include('portal.schedule-days', ['days' => $upcoming])
        <a class="button button-light schedule-more" href="{{ route('portal.schedule') }}">{{ __('portal.dashboard.full_schedule') }}</a>
    </div>

    @foreach ($members as $entry)
        @php($member = $entry['member'])

        <div class="card">
            <div class="section-head" style="margin-top: 0">
                <h3>{{ __('portal.dashboard.details') }}{{ $members->count() > 1 ? ' — '.$member->first_name : '' }}</h3>
                @unless ($member->hasLeft())
                    <a href="{{ route('portal.edit', $member) }}">{{ __('portal.dashboard.edit') }}</a>
                @endunless
            </div>
            <dl>
                <dt>{{ __('portal.fields.email') }}</dt><dd>{{ $member->email ?: '—' }}</dd>
                <dt>{{ __('portal.fields.phone_mobile') }}</dt><dd>{{ $member->phone_mobile ?: '—' }}</dd>
                <dt>{{ __('portal.fields.phone_home') }}</dt><dd>{{ $member->phone_home ?: '—' }}</dd>
                <dt>{{ __('portal.fields.address') }}</dt><dd>{{ collect([$member->street, trim($member->postal_code.' '.$member->city), $member->country])->filter()->implode(', ') ?: '—' }}</dd>
                <dt>{{ __('portal.fields.joined_at') }}</dt><dd>{{ $member->joined_at?->format('d-m-Y') ?? '—' }}</dd>
            </dl>

            @if ($member->memberships->isNotEmpty())
                <h3>{{ __('portal.dashboard.memberships') }}</h3>
                <ul class="history">
                    @foreach ($member->memberships as $membership)
                        <li><span>{{ __('portal.dashboard.period', ['from' => $membership->starts_at->format('d-m-Y'), 'until' => $membership->ends_at->format('d-m-Y')]) }}</span><span>{{ $membership->status->getLabel() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach

    @if ($contact)
        <p class="muted" style="margin-top: 1rem">{!! __('portal.dashboard.contact', ['email' => '<a href="mailto:'.e($contact).'">'.e($contact).'</a>']) !!}</p>
    @endif
@endsection
