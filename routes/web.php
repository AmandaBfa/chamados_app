<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MfaChallengeController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TicketAttachmentController;
use App\Services\CatalogDefinition;
use App\Services\PortalAccess;
use App\Services\TicketOperations;
use Illuminate\Http\Request;
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
        Route::middleware('admin.mfa')->group(function () {
            Route::view('/ti/chamados', 'ti.queue')->name('ti.index');
            Route::get('/ti/chamados/{ticket}', function (int $ticket) {
                app(TicketOperations::class)->ticket(auth()->user(), $ticket);

                return view('ti.detail', compact('ticket'));
            })->whereNumber('ticket')->name('ti.show');
            Route::get('/ti/chamados/{ticket}/anexos/{attachment}', [TicketAttachmentController::class, 'staff'])
                ->whereNumber(['ticket', 'attachment'])->name('ti.attachment');
            Route::view('/meus-chamados', 'portal.index')->name('portal.index');
            Route::get('/chamados/abrir', function (Request $request) {
                $related = $request->integer('relacionado') ?: null;
                if ($related !== null) {
                    app(PortalAccess::class)->own(auth()->user(), $related);
                }

                return view('portal.open', compact('related'));
            })->name('portal.open');
            Route::get('/meus-chamados/{ticket}', function (int $ticket) {
                app(PortalAccess::class)->own(auth()->user(), $ticket);

                return view('portal.show', compact('ticket'));
            })->whereNumber('ticket')->name('portal.show');
            Route::get('/meus-chamados/{ticket}/anexos/{attachment}', TicketAttachmentController::class)
                ->whereNumber(['ticket', 'attachment'])->name('portal.attachment');
        });
        Route::view('/inicio', 'home')->middleware('admin.mfa')->name('home');
        Route::middleware(['admin.mfa', 'password.confirm'])->group(function () {
            Route::get('/administracao/sla', [SlaPolicyController::class, 'index'])->name('sla.index');
            Route::post('/administracao/sla', [SlaPolicyController::class, 'store'])->name('sla.store');
            Route::post('/administracao/sla/{version}/validar', [SlaPolicyController::class, 'approve'])
                ->whereNumber('version')->name('sla.approve');
        });
        Route::middleware(['password.confirm', 'throttle:access-sensitive'])->group(function () {
            Route::get('/seguranca', [SecurityController::class, 'show'])->name('security.show');
            Route::post('/seguranca/ativar', [SecurityController::class, 'enable'])->name('security.enable');
            Route::post('/seguranca/confirmar', [SecurityController::class, 'confirm'])->name('security.confirm');
            Route::post('/seguranca/codigos', [SecurityController::class, 'regenerate'])->name('security.codes');
        });
        Route::view('/administracao/usuarios', 'admin.users')
            ->middleware(['admin.mfa', 'can:users.view', 'password.confirm'])->name('admin.users');
        Route::get('/administracao/cadastros/{kind}', function (string $kind) {
            CatalogDefinition::get($kind);

            return view('admin.catalogs', compact('kind'));
        })->middleware(['admin.mfa', 'can:catalogs.manage', 'password.confirm'])->name('admin.catalogs');
    });
});
