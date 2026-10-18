<?php

use App\Http\Controllers\SignUpController;
use App\Http\Controllers\VerifyCardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public page behind the QR code on membership cards. "/verify.php" keeps cards from the old ledenpas tool working.
Route::get('/verify', VerifyCardController::class)->middleware('throttle:60,1')->name('card.verify');
Route::get('/verify.php', VerifyCardController::class)->middleware('throttle:60,1');

// Public sign-up form.
Route::get('/aanmelden', [SignUpController::class, 'create'])->name('signup');
Route::post('/aanmelden', [SignUpController::class, 'store'])->middleware('throttle:signup')->name('signup.store');
Route::get('/aanmelden/bedankt', [SignUpController::class, 'thanks'])->name('signup.thanks');
