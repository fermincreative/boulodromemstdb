{{-- Navigation and logout, on every page of the ledenportaal for logged-in members. --}}
<nav class="portal-nav" aria-label="{{ __('portal.subtitle') }}">
    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('portal.nav.membership') }}</a>
    <a href="{{ route('portal.schedule') }}" @if (request()->routeIs('portal.schedule')) aria-current="page" @endif>{{ __('portal.nav.schedule') }}</a>
</nav>
<div class="topbar">
    <span>{{ __('portal.dashboard.logged_in_as', ['email' => $account->email]) }}</span>
    <form method="post" action="{{ route('portal.logout') }}">
        @csrf
        <button type="submit" class="link-button">{{ __('portal.dashboard.log_out') }}</button>
    </form>
</div>
