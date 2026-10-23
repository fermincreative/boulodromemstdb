<?php

use App\Http\Middleware\PublicSecurityHeaders;
use App\Http\Middleware\SetPublicLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mollie cannot send a CSRF token; the webhook checks every status with Mollie itself.
        $middleware->validateCsrfTokens(except: ['mollie/webhook']);
        // Members who are not logged in go to the login on the main domain (the admin panel has its own login).
        $middleware->redirectGuestsTo(fn () => route('home'));
        $middleware->alias(['public.locale' => SetPublicLocale::class, 'public.headers' => PublicSecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
