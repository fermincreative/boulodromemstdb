<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('membership_card.association_name') }}</title>
    @stack('head')
    <style>
        :root { --accent: #E5D0A7; --accent-dark: #8a6d3b; --ink: #111; --muted: #6b6b6b; --line: #e4e4e7; --error: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f4f5; color: var(--ink); line-height: 1.5; }
        header { background: #000; color: var(--accent); text-align: center; padding: 1.5rem 1rem 1.25rem; border-bottom: 6px solid var(--accent); }
        header h1 { margin: 0; font-size: 1.5rem; }
        header p { margin: .35rem 0 0; font-size: .75rem; letter-spacing: .25em; color: #AC9C7D; text-transform: uppercase; }
        .languages { display: flex; justify-content: center; gap: .9rem; margin-top: .6rem; font-size: .8rem; }
        .languages a { color: #AC9C7D; text-decoration: none; }
        .languages a[aria-current] { color: var(--accent); text-decoration: underline; }
        main { max-width: 44rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .card { background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 2px rgb(0 0 0 / .05); }
        .card + .card { margin-top: 1rem; }
        h2 { font-size: 1.1rem; margin: 0 0 1rem; }
        p { margin: 0 0 1rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        a { color: var(--accent-dark); }
    </style>
</head>
<body>
    <header>
        <h1>{{ config('membership_card.association_name') }}</h1>
        <p>@yield('subtitle')</p>
        {{-- Only when more than one language is enabled (PUBLIC_LOCALES). --}}
        @if (count(config('app.public_locales')) > 1)
            <nav class="languages" aria-label="Taal / Language">
                @foreach (config('app.public_locales') as $locale)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}" lang="{{ $locale }}" @if (app()->getLocale() === $locale) aria-current="true" @endif>{{ config('app.locale_names')[$locale] ?? strtoupper($locale) }}</a>
                @endforeach
            </nav>
        @endif
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
