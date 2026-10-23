<?php

use App\Http\Controllers\CardCheckController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Controllers\SignUpController;
use App\Http\Controllers\VerifyCardController;
use Illuminate\Support\Facades\Route;

// Public pages in the visitor's language (PUBLIC_LOCALES; Dutch only for now).
Route::middleware('public.locale')->group(function () {
    // Ledenportaal: members log in with a link sent by e-mail (staff use /admin).
    Route::get('/', [MemberPortalController::class, 'home'])->name('home');
    Route::post('/inloggen', [MemberPortalController::class, 'requestLink'])->middleware('throttle:member-login')->name('portal.request');
    Route::get('/inloggen/{token}', [MemberPortalController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('portal.confirm');
    Route::post('/inloggen/{token}', [MemberPortalController::class, 'logIn'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('portal.login');
    Route::middleware('auth:member')->group(function () {
        Route::post('/uitloggen', [MemberPortalController::class, 'logOut'])->name('portal.logout');
        Route::get('/mijn/kaart/{membership}/{variant}', [MemberPortalController::class, 'card'])->name('portal.card');
        Route::get('/rooster', [MemberPortalController::class, 'schedule'])->name('portal.schedule');
        Route::get('/mijn/gegevens/{member}', [MemberPortalController::class, 'edit'])->name('portal.edit');
        Route::put('/mijn/gegevens/{member}', [MemberPortalController::class, 'update'])->name('portal.update');
    });
    // Confirming a new e-mail address works from the link in that mailbox, also when not logged in on that device.
    Route::middleware(['signed', 'throttle:30,1'])->group(function () {
        Route::get('/mijn/e-mailadres/{member}', [MemberPortalController::class, 'confirmEmail'])->name('portal.email.confirm');
        Route::post('/mijn/e-mailadres/{member}', [MemberPortalController::class, 'applyEmail'])->name('portal.email.apply');
    });

    // Public sign-up form.
    Route::get('/aanmelden', [SignUpController::class, 'create'])->name('signup');
    Route::post('/aanmelden', [SignUpController::class, 'store'])->middleware('throttle:signup')->name('signup.store');
    Route::get('/aanmelden/bedankt', [SignUpController::class, 'thanks'])->name('signup.thanks');

    // Paying an open payment (link in the aanmelding and verlenging e-mails), and Mollie's status updates.
    Route::middleware(['signed', 'throttle:30,1'])->group(function () {
        Route::get('/betalen/{payment}', [OnlinePaymentController::class, 'show'])->name('payment.show');
        Route::post('/betalen/{payment}', [OnlinePaymentController::class, 'start'])->name('payment.start');
        Route::get('/betalen/{payment}/klaar', [OnlinePaymentController::class, 'return'])->name('payment.return');
    });
});

// Public page behind the QR code on membership cards. "/verify.php" keeps cards from the old ledenpas tool working.
Route::get('/verify', VerifyCardController::class)->middleware('throttle:60,1')->name('card.verify');
Route::get('/verify.php', VerifyCardController::class)->middleware('throttle:60,1');
// Staff actions after scanning a card (admin login in the same browser; same permissions as the panel).
Route::get('/kaartcontrole/inloggen', [CardCheckController::class, 'logIn'])->name('card.check.login');
Route::middleware('auth:web')->group(function () {
    Route::post('/kaartcontrole/betaling/{payment}', [CardCheckController::class, 'collect'])->name('card.check.collect');
    Route::post('/kaartcontrole/verlengen/{member}', [CardCheckController::class, 'renew'])->name('card.check.renew');
});

Route::post('/mollie/webhook', MollieWebhookController::class)->middleware('throttle:120,1')->name('mollie.webhook');
