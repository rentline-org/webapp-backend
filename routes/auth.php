<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('web');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('web');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('web');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('web');

Route::post('/verify-otp', [OtpVerificationController::class, 'verify'])
    ->middleware(['web', 'throttle:5,1']);

Route::post('/resend-otp', [OtpVerificationController::class, 'resend'])
    ->middleware(['web', 'throttle:5,1']);

// Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
//     ->middleware(['auth:sanctum', 'signed', 'throttle:6,1']);
// ->name('verification.verify');

// Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
//     ->middleware(['auth:sanctum', 'throttle:6,1']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth');
