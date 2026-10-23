@extends('layouts.public')

@section('title', __('portal.email_change.title'))
@section('subtitle', __('portal.subtitle'))

@push('head')
    @include('portal.styles')
@endpush

@section('content')
    <div class="card">
        @if ($valid)
            <h2>{{ __('portal.email_change.title') }}</h2>
            <p>{!! __('portal.email_change.intro', ['name' => e($member->full_name), 'email' => e($email)]) !!}</p>
            <form method="post" action="{{ $action }}">
                @csrf
                <button type="submit" class="button button-block">{{ __('portal.email_change.submit') }}</button>
            </form>
        @else
            <h2>{{ __('portal.email_change.invalid_title') }}</h2>
            <p>{{ __('portal.email_change.invalid') }}</p>
            <a class="button" href="{{ route('home') }}">{{ __('portal.email_change.to_portal') }}</a>
        @endif
    </div>
@endsection
