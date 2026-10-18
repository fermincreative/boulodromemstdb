@extends('layouts.public')

@section('title', 'Bedankt voor uw aanmelding')
@section('subtitle', 'Lid worden')

@push('head')
    <style>
        dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1.25rem; margin: 0 0 1rem; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; word-break: break-word; }
    </style>
@endpush

@section('content')
    <div class="card">
        @if ($signup)
            <h2>Bedankt voor uw aanmelding, {{ $signup['name'] }}!</h2>
            <p>Uw lidnummer is <strong>{{ $signup['number'] }}</strong>. Wij hebben de betaalgegevens ook naar <strong>{{ $signup['email'] }}</strong> gestuurd.</p>
        @else
            <h2>Bedankt voor uw aanmelding!</h2>
            <p>U ontvangt binnen enkele minuten een e-mail met de betaalgegevens.</p>
        @endif
    </div>

    @if ($signup)
        <div class="card">
            <h2>Contributie betalen</h2>
            <p>{{ $signup['description'] }}: <strong>{{ $signup['amount'] }}</strong>. Wilt u dit bedrag overmaken naar:</p>
            <dl>
                <dt>Rekeningnummer</dt><dd>{{ $signup['iban'] }}</dd>
                <dt>Ten name van</dt><dd>{{ $signup['holder'] }}</dd>
                <dt>Bedrag</dt><dd>{{ $signup['amount'] }}</dd>
                <dt>Omschrijving</dt><dd>{{ $signup['reference'] }}</dd>
            </dl>
            <p class="muted">Zodra wij uw betaling hebben ontvangen, sturen wij uw lidmaatschapskaart per e-mail.</p>
        </div>
    @endif
@endsection
