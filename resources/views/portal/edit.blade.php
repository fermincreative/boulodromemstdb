@extends('layouts.public')

@section('title', __('portal.edit.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
    <style>
        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: .9rem 1rem; }
        .field { grid-column: span 12; display: flex; flex-direction: column; gap: .3rem; }
        @media (min-width: 640px) { .s-3 { grid-column: span 3; } .s-4 { grid-column: span 4; } .s-5 { grid-column: span 5; } .s-6 { grid-column: span 6; } }
        .field label { margin-bottom: 0; }
        .optional { font-weight: 400; color: var(--muted); }
        .field input { font: inherit; padding: .65rem .75rem; border: 1px solid #c8c8cc; border-radius: .5rem; background: #fff; width: 100%; }
        .field input:focus { outline: 2px solid var(--accent-dark); outline-offset: 1px; border-color: var(--accent-dark); }
        .invalid input { border-color: var(--error); }
        .hint { color: var(--muted); font-size: .8rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-top: 1.25rem; }
    </style>
@endpush

@section('content')
    <form method="post" action="{{ route('portal.update', $member) }}" class="card" novalidate>
        @csrf
        @method('PUT')

        <h2>{{ __('portal.edit.title') }}</h2>
        <p class="muted">{{ __('portal.edit.intro', ['name' => $member->full_name]) }}</p>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">{{ __('portal.edit.check_fields') }}</div>
        @endif

        <div class="grid">
            @include('signup.field', ['name' => 'email', 'label' => __('portal.fields.email'), 'class' => '', 'type' => 'email', 'autocomplete' => 'email', 'required' => true, 'value' => $member->email,
                'hint' => __('portal.edit.email_hint').($sharedWith->isNotEmpty() ? ' '.__('portal.edit.email_shared', ['names' => $sharedWith->implode(', ')]) : '')])
            @include('signup.field', ['name' => 'phone_mobile', 'label' => __('portal.fields.phone_mobile'), 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel', 'value' => $member->phone_mobile])
            @include('signup.field', ['name' => 'phone_home', 'label' => __('portal.fields.phone_home'), 'class' => 's-6', 'type' => 'tel', 'autocomplete' => 'tel', 'value' => $member->phone_home])
            @include('signup.field', ['name' => 'street', 'label' => __('portal.fields.street'), 'class' => '', 'autocomplete' => 'street-address', 'required' => true, 'value' => $member->street])
            @include('signup.field', ['name' => 'postal_code', 'label' => __('portal.fields.postal_code'), 'class' => 's-3', 'autocomplete' => 'postal-code', 'required' => true, 'value' => $member->postal_code])
            @include('signup.field', ['name' => 'city', 'label' => __('portal.fields.city'), 'class' => 's-5', 'autocomplete' => 'address-level2', 'required' => true, 'value' => $member->city])
            @include('signup.field', ['name' => 'country', 'label' => __('portal.fields.country'), 'class' => 's-4', 'autocomplete' => 'country-name', 'required' => true, 'value' => $member->country])
        </div>

        <div class="actions">
            <button type="submit" class="button">{{ __('portal.edit.save') }}</button>
            <a href="{{ route('home') }}">{{ __('portal.edit.cancel') }}</a>
        </div>
    </form>
@endsection
