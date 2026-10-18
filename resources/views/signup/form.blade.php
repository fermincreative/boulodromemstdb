@extends('layouts.public')

@section('title', 'Lid worden')
@section('subtitle', 'Lid worden')

@push('head')
    <style>
        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: .9rem 1rem; }
        .field { grid-column: span 12; display: flex; flex-direction: column; gap: .3rem; }
        @media (min-width: 640px) {
            .s-2 { grid-column: span 2; } .s-3 { grid-column: span 3; } .s-4 { grid-column: span 4; }
            .s-5 { grid-column: span 5; } .s-6 { grid-column: span 6; } .s-8 { grid-column: span 8; }
        }
        label { font-weight: 600; font-size: .9rem; }
        .optional { font-weight: 400; color: var(--muted); }
        input, select { font: inherit; padding: .65rem .75rem; border: 1px solid #c8c8cc; border-radius: .5rem; background: #fff; width: 100%; }
        input:focus, select:focus { outline: 2px solid var(--accent-dark); outline-offset: 1px; border-color: var(--accent-dark); }
        .invalid input, .invalid select { border-color: var(--error); }
        .error { color: var(--error); font-size: .85rem; }
        .hint { color: var(--muted); font-size: .8rem; }
        fieldset { border: 0; padding: 0; margin: 0 0 1.5rem; }
        legend { font-weight: 700; font-size: 1.05rem; margin-bottom: .75rem; }
        .fee { background: #faf6ee; border: 1px solid var(--accent); border-radius: .75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; }
        .fee strong { font-size: 1.15rem; }
        .consent { display: flex; gap: .6rem; align-items: flex-start; }
        .consent input { width: auto; margin-top: .3rem; }
        .consent label { font-weight: 400; }
        button { font: inherit; font-weight: 700; background: #000; color: var(--accent); border: 0; border-radius: .6rem; padding: .85rem 1.5rem; cursor: pointer; width: 100%; }
        button:hover { background: #222; }
        .alert { background: #fee2e2; color: var(--error); border-radius: .6rem; padding: .75rem 1rem; margin-bottom: 1rem; }
        /* Trap for bots: invisible to people (also to screen readers). */
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    </style>
@endpush

@section('content')
    @unless ($enabled)
        <div class="card">
            <h2>Aanmelden is op dit moment niet mogelijk</h2>
            <p>Neem voor een lidmaatschap contact op met de ledenadministratie van {{ config('membership_card.association_name') }}.</p>
        </div>
    @else
        <form method="post" action="{{ route('signup.store') }}" class="card" novalidate>
            @csrf
            <input type="hidden" name="form_token" value="{{ $formToken }}">
            <div class="hp" aria-hidden="true">
                <label for="website">Laat dit veld leeg</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h2>Word lid van {{ config('membership_card.association_name') }}</h2>
            <p class="muted">Vul het formulier in. U ontvangt daarna per e-mail de betaalgegevens; zodra uw betaling binnen is, sturen wij uw lidmaatschapskaart.</p>

            <div class="fee" role="note">
                Contributie bij aanmelding vandaag: <strong>{{ $quote->summary() }}</strong>.<br>
                <span class="muted">Het lidmaatschap loopt tot en met 31 december{{ count($quote->periods) > 1 ? ' van volgend jaar' : '' }}.</span>
            </div>

            @error('form')
                <div class="alert" role="alert">{{ $message }}</div>
            @enderror
            @if ($errors->any() && ! $errors->has('form'))
                <div class="alert" role="alert">Controleer de gemarkeerde velden.</div>
            @endif


            <fieldset>
                <legend>Persoonsgegevens</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'first_name', 'label' => 'Voornaam', 'class' => 's-5', 'autocomplete' => 'given-name', 'required' => true])
                    @include('signup.field', ['name' => 'middle_name', 'label' => 'Tussenvoegsel', 'class' => 's-2', 'autocomplete' => 'additional-name'])
                    @include('signup.field', ['name' => 'last_name', 'label' => 'Achternaam', 'class' => 's-5', 'autocomplete' => 'family-name', 'required' => true])
                    @include('signup.field', ['name' => 'birthday', 'label' => 'Geboortedatum', 'class' => 's-4', 'autocomplete' => 'bday', 'placeholder' => 'dd-mm-jjjj', 'hint' => 'Bijv. 10-09-1992', 'inputmode' => 'numeric'])
                </div>
            </fieldset>

            <fieldset>
                <legend>Contact</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'email', 'label' => 'E-mailadres', 'class' => '', 'type' => 'email', 'autocomplete' => 'email', 'required' => true, 'hint' => 'Hierop ontvangt u de betaalgegevens en uw lidmaatschapskaart.'])
                    @include('signup.field', ['name' => 'phone_mobile', 'label' => 'Mobiel nummer', 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel'])
                    @include('signup.field', ['name' => 'phone_home', 'label' => 'Telefoon thuis', 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel'])
                </div>
            </fieldset>

            <fieldset>
                <legend>Adres</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'street', 'label' => 'Straat en huisnummer', 'class' => '', 'autocomplete' => 'street-address', 'required' => true])
                    @include('signup.field', ['name' => 'postal_code', 'label' => 'Postcode', 'class' => 's-3', 'autocomplete' => 'postal-code', 'required' => true])
                    @include('signup.field', ['name' => 'city', 'label' => 'Woonplaats', 'class' => 's-5', 'autocomplete' => 'address-level2', 'required' => true])
                    <div class="field s-4 @error('country') invalid @enderror">
                        <label for="country">Land</label>
                        <select id="country" name="country" autocomplete="country-name" required>
                            @foreach (['Nederland', 'België', 'Duitsland', 'Anders'] as $country)
                                <option value="{{ $country }}" @selected(old('country', 'Nederland') === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                        @error('country') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Vereniging</legend>
                <div class="grid">
                    <div class="field s-8 @error('club_id') invalid @enderror">
                        <label for="club_id">Bent u lid van een van deze verenigingen? <span class="optional">(optioneel)</span></label>
                        <select id="club_id" name="club_id">
                            <option value="">Geen</option>
                            @foreach ($clubs as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('club_id') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('club_id') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Privacy</legend>
                <p class="muted">Wij gebruiken uw gegevens alleen voor de ledenadministratie: uw lidmaatschap, de contributie, uw lidmaatschapskaart en berichten over de vereniging. Wij delen ze niet met anderen. U kunt uw gegevens altijd laten inzien, aanpassen of verwijderen via de ledenadministratie.</p>
                <div class="consent @error('consent') invalid @enderror">
                    <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent')) required>
                    <label for="consent">Ik ga ermee akkoord dat {{ config('membership_card.association_name') }} mijn gegevens hiervoor gebruikt.</label>
                </div>
                @error('consent') <span class="error">{{ $message }}</span> @enderror
            </fieldset>

            <button type="submit">Aanmelden</button>
        </form>
    @endunless
@endsection
