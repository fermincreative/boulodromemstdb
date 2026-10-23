@extends('layouts.public')

@section('title', __('signup.title'))
@section('subtitle', __('signup.subtitle'))

@push('head')
    <style>
        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: .9rem 1rem; }
        /* Each field spans three shared rows (label, input, hint), so inputs side by side line up even when one label wraps. */
        .field { grid-column: span 12; grid-row: span 3; display: grid; grid-template-rows: subgrid; row-gap: .3rem; }
        .field label { align-self: end; }
        @supports not (grid-template-rows: subgrid) {
            .field { grid-row: auto; display: flex; flex-direction: column; gap: .3rem; }
        }
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
            <h2>{{ __('signup.closed_title') }}</h2>
            <p>{{ __('signup.closed', ['association' => config('membership_card.association_name')]) }}</p>
        </div>
    @else
        <form method="post" action="{{ route('signup.store') }}" class="card" novalidate>
            @csrf
            <input type="hidden" name="form_token" value="{{ $formToken }}">
            <div class="hp" aria-hidden="true">
                <label for="website">{{ __('signup.honeypot') }}</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h2>{{ __('signup.heading', ['association' => config('membership_card.association_name')]) }}</h2>
            <p class="muted">
                {{ $payOnline ? __('signup.intro_online', ['methods' => $onlineMethods]) : __('signup.intro_transfer') }}
                {{ __('signup.intro_card') }}
            </p>
            @if ($signUpClub || $collectiveClubs)
                <p class="muted">
                    @if ($signUpClub)
                        {{ __('signup.club', ['club' => $signUpClub]) }}
                    @endif
                    @if ($collectiveClubs)
                        {{ __('signup.collective_clubs', ['clubs' => $collectiveClubs]) }}
                    @endif
                </p>
            @endif

            <div class="fee" role="note">
                {!! __('signup.fee', ['summary' => e($quote->summary())]) !!}<br>
                <span class="muted">{{ count($quote->periods) > 1 ? __('signup.runs_until_next_year') : __('signup.runs_until') }}</span>
            </div>

            @error('form')
                <div class="alert" role="alert">{{ $message }}</div>
            @enderror
            @if ($errors->any() && ! $errors->has('form'))
                <div class="alert" role="alert">{{ __('signup.check_fields') }}</div>
            @endif

            <fieldset>
                <legend>{{ __('signup.sections.personal') }}</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'first_name', 'label' => __('signup.fields.first_name'), 'class' => 's-5', 'autocomplete' => 'given-name', 'required' => true])
                    @include('signup.field', ['name' => 'middle_name', 'label' => __('signup.fields.middle_name'), 'class' => 's-2', 'autocomplete' => 'additional-name'])
                    @include('signup.field', ['name' => 'last_name', 'label' => __('signup.fields.last_name'), 'class' => 's-5', 'autocomplete' => 'family-name', 'required' => true])
                    @include('signup.field', ['name' => 'birthday', 'label' => __('signup.fields.birthday'), 'class' => 's-4', 'autocomplete' => 'bday', 'placeholder' => __('signup.fields.birthday_placeholder'), 'hint' => __('signup.fields.birthday_hint'), 'inputmode' => 'numeric'])
                </div>
            </fieldset>

            <fieldset>
                <legend>{{ __('signup.sections.contact') }}</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'email', 'label' => __('signup.fields.email'), 'class' => '', 'type' => 'email', 'autocomplete' => 'email', 'required' => true, 'hint' => __('signup.fields.email_hint')])
                    @include('signup.field', ['name' => 'phone_mobile', 'label' => __('signup.fields.phone_mobile'), 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel'])
                    @include('signup.field', ['name' => 'phone_home', 'label' => __('signup.fields.phone_home'), 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel'])
                </div>
            </fieldset>

            <fieldset>
                <legend>{{ __('signup.sections.address') }}</legend>
                <div class="grid">
                    @include('signup.field', ['name' => 'street', 'label' => __('signup.fields.street'), 'class' => '', 'autocomplete' => 'street-address', 'required' => true])
                    @include('signup.field', ['name' => 'postal_code', 'label' => __('signup.fields.postal_code'), 'class' => 's-3', 'autocomplete' => 'postal-code', 'required' => true])
                    @include('signup.field', ['name' => 'city', 'label' => __('signup.fields.city'), 'class' => 's-5', 'autocomplete' => 'address-level2', 'required' => true])
                    <div class="field s-4 @error('country') invalid @enderror">
                        <label for="country">{{ __('signup.fields.country') }}</label>
                        <select id="country" name="country" autocomplete="country-name" required>
                            @foreach (__('signup.countries') as $country => $label)
                                <option value="{{ $country }}" @selected(old('country', 'Nederland') === $country)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('country') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>{{ __('signup.sections.privacy') }}</legend>
                <p class="muted">{{ __('signup.privacy') }}</p>
                <div class="consent @error('consent') invalid @enderror">
                    <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent')) required>
                    <label for="consent">{{ __('signup.consent', ['association' => config('membership_card.association_name')]) }}</label>
                </div>
                @error('consent') <span class="error">{{ $message }}</span> @enderror
            </fieldset>

            <button type="submit">{{ $payOnline ? __('signup.submit_pay') : __('signup.submit') }}</button>
        </form>
    @endunless
@endsection
