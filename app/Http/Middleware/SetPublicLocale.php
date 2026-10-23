<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the language of the public pages (sign-up, payment, ledenportaal) from the enabled ones (PUBLIC_LOCALES):
 * "?lang=en" (remembered for the session), else the browser's preference, else the default. The admin panel stays Dutch.
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = config('app.public_locales');
        // The first enabled language is the default. (Not config('app.locale'): setLocale() below overwrites that.)
        $default = $enabled[0] ?? 'nl';

        if (in_array($request->query('lang'), $enabled, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }

        $locale = $request->session()->get('locale');

        if (! in_array($locale, $enabled, true)) {
            $locale = $request->getPreferredLanguage($enabled) ?? $default;
        }

        app()->setLocale(in_array($locale, $enabled, true) ? $locale : $default);

        return $next($request);
    }
}
