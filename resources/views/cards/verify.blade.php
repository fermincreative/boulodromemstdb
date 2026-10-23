<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $result->title }} — {{ config('membership_card.association_name') }}</title>
    <style>
        :root { --accent: #E5D0A7; --ok: #15803d; --ok-bg: #dcfce7; --bad: #b91c1c; --bad-bg: #fee2e2; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f4f5; color: #111; }
        header { background: #000; color: var(--accent); text-align: center; padding: 1.25rem 1rem 1rem; border-bottom: 6px solid var(--accent); }
        header h1 { margin: 0; font-size: 1.35rem; }
        header p { margin: .35rem 0 0; font-size: .7rem; letter-spacing: .25em; color: #AC9C7D; }
        main { max-width: 26rem; margin: 0 auto; padding: 1.25rem 1rem 2rem; }
        .status { border-radius: 1rem; padding: 1.5rem 1rem; text-align: center; }
        .status.ok { background: var(--ok-bg); color: var(--ok); }
        .status.bad { background: var(--bad-bg); color: var(--bad); }
        .icon { width: 5.5rem; height: 5.5rem; margin: 0 auto .75rem; border-radius: 50%; display: grid; place-items: center; color: #fff; }
        .ok .icon { background: var(--ok); }
        .bad .icon { background: var(--bad); }
        .icon svg { width: 3.25rem; height: 3.25rem; }
        .status h2 { margin: 0; font-size: 1.75rem; }
        .status p { margin: .5rem 0 0; font-size: 1rem; color: #333; }
        .member { background: #fff; border-radius: 1rem; margin-top: 1rem; padding: 1rem 1.25rem; }
        .member .name { font-size: 1.2rem; font-weight: 700; }
        .member dl { display: grid; grid-template-columns: auto 1fr; gap: .35rem 1rem; margin: .75rem 0 0; font-size: .95rem; }
        .member dt { color: #71717a; }
        .member dd { margin: 0; font-weight: 600; }
        .year { display: inline-block; padding: .05rem .55rem; border-radius: .4rem; font-weight: 700; }
        .notice { margin-top: 1rem; background: #fef9c3; color: #854d0e; border-radius: .75rem; padding: .75rem 1rem; font-size: .9rem; }
        .checked { text-align: center; color: #71717a; font-size: .8rem; margin-top: 1.25rem; }
        /* Staff only (logged in to the admin panel in this browser). */
        .flash { margin-top: 1rem; border-radius: .75rem; padding: .75rem 1rem; font-size: .95rem; }
        .flash.ok { background: var(--ok-bg); color: var(--ok); }
        .flash.bad { background: var(--bad-bg); color: var(--bad); }
        .manage { background: #fff; border-radius: 1rem; margin-top: 1rem; padding: 1rem 1.25rem; border: 2px solid var(--accent); }
        .manage h3 { margin: 0 0 .75rem; font-size: 1rem; display: flex; justify-content: space-between; gap: .5rem; }
        .manage h3 span { font-weight: 400; color: #71717a; font-size: .8rem; }
        .manage dl { display: grid; grid-template-columns: auto 1fr; gap: .35rem 1rem; margin: 0 0 .75rem; font-size: .95rem; }
        .manage dt { color: #71717a; }
        .manage dd { margin: 0; overflow-wrap: anywhere; }
        .manage a { color: #8a6d3b; }
        .payment { border-top: 1px solid #e4e4e7; padding: .6rem 0; }
        .payment p { margin: 0 0 .5rem; }
        .manage form { margin: 0; }
        .btn { display: block; width: 100%; font: inherit; font-weight: 700; border: 0; border-radius: .6rem; padding: .8rem 1rem; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-pay { background: var(--ok); color: #fff; }
        .btn-dark { background: #000; color: var(--accent) !important; margin-top: .5rem; }
        .btn-light { background: #f4f4f5; color: #111 !important; margin-top: .5rem; }
        .hint { font-size: .85rem; color: #71717a; margin: .5rem 0; }
        .staff-login { text-align: center; font-size: .8rem; margin-top: .5rem; }
        .staff-login a { color: #a1a1aa; }
    </style>
</head>
<body>
    <header>
        <h1>{{ config('membership_card.association_name') }}</h1>
        <p>KAARTCONTROLE</p>
    </header>

    <main>
        <section class="status {{ $result->valid ? 'ok' : 'bad' }}" role="status">
            <div class="icon" aria-hidden="true">
                @if ($result->valid)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                @endif
            </div>
            <h2>{{ $result->title }}</h2>
            @if ($result->message)
                <p>{{ $result->message }}</p>
            @endif
        </section>

        @if ($result->member)
            <section class="member">
                <div class="name">{{ $result->member->full_name }}</div>
                <dl>
                    <dt>Lidnummer</dt>
                    <dd>{{ $result->member->display_number }}</dd>
                    @if ($result->membership)
                        @php($yearStyle = app(\App\Services\CardYearStyles::class)->for($result->membership->cardYear()))
                        <dt>Jaar</dt>
                        <dd><span class="year" style="background: {{ $yearStyle['badge'] }}; color: {{ $yearStyle['text'] }}">{{ $result->membership->cardYear() }}</span></dd>
                    @endif
                </dl>
            </section>
        @endif

        @if ($result->notice)
            <div class="notice">{{ $result->notice }}</div>
        @endif

        @if (session('card_check'))
            <div class="flash ok" role="status">{{ session('card_check') }}</div>
        @endif
        @if (session('card_check_error'))
            <div class="flash bad" role="alert">{{ session('card_check_error') }}</div>
        @endif

        @if ($manage)
            @php($member = $manage['member'])
            <section class="manage" aria-label="Beheer">
                <h3>Beheer <span>{{ $staff->name }}</span></h3>
                <dl>
                    <dt>Vereniging</dt>
                    <dd>{{ $member->club?->name ?? 'Geen' }}{{ $member->paysCollectively() ? ' (betaalt de contributie)' : '' }}</dd>
                    <dt>E-mail</dt>
                    <dd>@if ($member->email)<a href="mailto:{{ $member->email }}">{{ $member->email }}</a>@else — @endif</dd>
                    <dt>Mobiel</dt>
                    <dd>@if ($member->phone_mobile)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone_mobile) }}">{{ $member->phone_mobile }}</a>@else — @endif</dd>
                    @if ($member->phone_home)
                        <dt>Thuis</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone_home) }}">{{ $member->phone_home }}</a></dd>
                    @endif
                </dl>

                @foreach ($manage['openPayments'] as $payment)
                    <div class="payment">
                        <p>Openstaand: {{ $payment->description }} — <strong>{{ \App\Support\Money::format($payment->amount_cents) }}</strong></p>
                        @if ($manage['payments']->contains($payment))
                            <form method="post" action="{{ route('card.check.collect', $payment) }}"
                                  onsubmit="return confirm('{{ \App\Support\Money::format($payment->amount_cents) }} contant ontvangen van {{ addslashes($member->full_name) }}?')">
                                @csrf
                                <input type="hidden" name="return" value="{{ request()->fullUrl() }}">
                                <button type="submit" class="btn btn-pay">Contant ontvangen</button>
                            </form>
                        @endif
                    </div>
                @endforeach

                @if ($manage['renewYear'])
                    <div class="payment">
                        <p>Nog niet verlengd voor {{ $manage['renewYear'] }}.</p>
                        <form method="post" action="{{ route('card.check.renew', $member) }}"
                              onsubmit="return confirm('{{ addslashes($member->full_name) }} verlengen voor {{ $manage['renewYear'] }} en {{ \App\Support\Money::format($manage['renewFee']) }} contant ontvangen?')">
                            @csrf
                            <input type="hidden" name="return" value="{{ request()->fullUrl() }}">
                            <button type="submit" class="btn btn-pay">Verlengen voor {{ $manage['renewYear'] }} en {{ \App\Support\Money::format($manage['renewFee']) }} contant ontvangen</button>
                        </form>
                    </div>
                @endif

                @if (! $result->valid && $member->paysCollectively())
                    <p class="hint">De contributie wordt betaald door {{ $member->club->name }}. Activeer het lidmaatschap in de ledenadministratie (Verlengingen → Wacht op vereniging) zodra de vereniging heeft betaald.</p>
                @endif

                <a class="btn btn-dark" href="{{ $manage['viewUrl'] }}">Openen in ledenadministratie</a>
                @if ($manage['editUrl'])
                    <a class="btn btn-light" href="{{ $manage['editUrl'] }}">Gegevens wijzigen</a>
                @endif
            </section>
        @endif

        <p class="checked">Gecontroleerd op {{ $checkedAt->format('d-m-Y') }} om {{ $checkedAt->format('H:i') }}</p>
        @unless ($staff)
            <p class="staff-login"><a href="{{ route('card.check.login', ['return' => request()->fullUrl()]) }}">Beheerder? Inloggen</a></p>
        @endunless
    </main>
</body>
</html>
