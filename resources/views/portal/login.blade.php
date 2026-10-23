@extends('layouts.public')

@section('title', __('portal.login.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
@endpush

@section('content')
    <div class="card">
        <h2>{{ __('portal.login.title') }}</h2>

        @if ($sent)
            <div class="alert alert-info" role="status">
                {!! __('portal.login.sent', ['email' => e($sent), 'minutes' => \App\Actions\Accounts\SendLoginLink::VALID_MINUTES]) !!}
            </div>
        @endif
        @if (session('logged_out'))
            <div class="alert alert-info" role="status">{{ __('portal.login.logged_out') }}</div>
        @endif

        <p class="muted">{{ __('portal.login.intro') }}</p>

        <form method="post" action="{{ route('portal.request') }}" novalidate>
            @csrf
            <label for="email">{{ __('portal.login.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $sent) }}" autocomplete="email" required autofocus
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <span class="error" id="email-error">{{ $message }}</span> @enderror
            <button type="submit" class="button button-block">{{ __('portal.login.submit') }}</button>
        </form>
    </div>

    @if (config('members.signup_enabled'))
        <div class="card">
            <h2>{{ __('portal.login.not_a_member') }}</h2>
            <p class="muted">{{ __('portal.login.join', ['association' => config('membership_card.association_name')]) }}</p>
            <a class="button button-light" href="{{ route('signup') }}">{{ __('portal.login.sign_up') }}</a>
        </div>
    @endif
@endsection
