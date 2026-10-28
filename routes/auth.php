<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordOtpController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Lupa password: email -> OTP -> password baru
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');

    Route::get('verify-otp', [PasswordOtpController::class, 'create'])->name('password.otp');
    Route::post('verify-otp', [PasswordOtpController::class, 'store'])
        ->middleware('throttle:10,1')->name('password.otp.verify');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Verifikasi email tidak butuh login (tautan dibuka dari inbox, akun belum aktif).
Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('email/verify/{token}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')->name('verification.verify');
Route::post('email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')->name('verification.send');

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
