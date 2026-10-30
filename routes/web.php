<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MfaChallengeController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers as Fortify;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'home' : 'login'));
if (app()->environment(['local', 'testing'])) {
    Route::view('/verificacao', 'foundation')->name('foundation');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [Fortify\AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [Fortify\AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [Fortify\PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])->middleware('throttle:access-sensitive')->name('password.email');
    Route::get('/reset-password/{token}', [Fortify\NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [Fortify\NewPasswordController::class, 'store'])->middleware('throttle:access-sensitive')->name('password.update');
    Route::get('/two-factor-challenge', [Fortify\TwoFactorAuthenticatedSessionController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.login.store');
    Route::get('/convites/{token}', [InvitationController::class, 'show'])->middleware('throttle:20,1')->name('invitation.show');
    Route::post('/convites/{token}', [InvitationController::class, 'accept'])->middleware('throttle:access-sensitive')->name('invitation.accept');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Fortify\AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [Fortify\EmailVerificationPromptController::class, '__invoke'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Fortify\VerifyEmailController::class, '__invoke'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Fortify\EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/user/confirm-password', [Fortify\ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/user/confirm-password', [Fortify\ConfirmablePasswordController::class, 'store'])->middleware('throttle:access-sensitive')->name('password.confirm.store');

    Route::middleware('verified')->group(function () {
        Route::view('/inicio', 'home')->middleware('admin.mfa')->name('home');
        Route::middleware(['password.confirm', 'throttle:access-sensitive'])->group(function () {
            Route::get('/seguranca', [SecurityController::class, 'show'])->name('security.show');
            Route::post('/seguranca/ativar', [SecurityController::class, 'enable'])->name('security.enable');
            Route::post('/seguranca/confirmar', [SecurityController::class, 'confirm'])->name('security.confirm');
            Route::post('/seguranca/codigos', [SecurityController::class, 'regenerate'])->name('security.codes');
        });
        Route::view('/administracao/usuarios', 'admin.users')
            ->middleware(['admin.mfa', 'can:users.view', 'password.confirm'])->name('admin.users');
    });
});
