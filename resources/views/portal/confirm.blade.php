@extends('layouts.public')

@section('title', __('portal.login.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
@endpush

@section('content')
    <div class="card">
        @if ($usable)
            <h2>{{ __('portal.login.title') }}</h2>
            <p>{{ __('portal.confirm.intro') }}</p>
            <form method="post" action="{{ route('portal.login', ['token' => $token]) }}">
                @csrf
                <button type="submit" class="button button-block">{{ __('portal.confirm.submit') }}</button>
            </form>
        @else
            <h2>{{ __('portal.confirm.expired_title') }}</h2>
            <p>{{ __('portal.confirm.expired') }}</p>
            <a class="button" href="{{ route('home') }}">{{ __('portal.confirm.new_link') }}</a>
        @endif
    </div>
@endsection
