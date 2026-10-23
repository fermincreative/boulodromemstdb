<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extra browser protection for the public pages (sign-up, payment, ledenportaal, card check).
 * X-Frame-Options and X-Content-Type-Options already come from nginx; they are not sent twice, because
 * conflicting duplicates make browsers ignore them. The admin panel (Filament) is left alone.
 */
class PublicSecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = [
        "default-src 'self'",
        // The pages use their own inline styles and a few inline confirm() handlers; nothing is loaded from elsewhere.
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "object-src 'none'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', self::CONTENT_SECURITY_POLICY));
        // Pages with a token in the address (login links, signed payment links) must not leak it to other sites.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
