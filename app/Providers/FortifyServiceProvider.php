<?php

namespace App\Providers;

use App\Actions\ResetUserPassword;
use App\Enums\RoleName;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireAdministratorMfa;
use App\Models\User;
use App\Services\AccessAudit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rotas explícitas: não registrar cadastro público nem endpoints de remoção de MFA.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', mb_strtolower(trim((string) $request->input('email'))))->first();
            if ($user && $user->password && Hash::check((string) $request->input('password'), $user->password) && $user->canLoginLocally()) {
                if (Hash::needsRehash($user->password)) {
                    $user->forceFill(['password' => (string) $request->input('password')])->save();
                }

                return $user;
            }

            return null;
        });
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by('mfa:'.$r->session()->get('login.id', $r->ip())));
        RateLimiter::for('access-sensitive', fn (Request $r) => Limit::perMinute(5)->by('sensitive:'.($r->user()?->id ?? $r->ip())));
        foreach (['users.view', 'users.create', 'users.update', 'roles.assign'] as $ability) {
            Gate::define($ability, function (User $user) {
                return $user->active && $user->hasVerifiedEmail() && $user->hasRole(RoleName::Administrator) &&
                    $user->hasEnabledTwoFactorAuthentication() && session('mfa.user_id') === $user->id &&
                    (int) session('auth.version', -1) === $user->auth_version;
            });
        }
        Event::listen(Login::class, function (Login $event) {
            session(['auth.version' => $event->user->auth_version]);
            AccessAudit::record('auth.login', $event->user, $event->user);
        });
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                AccessAudit::record('auth.logout', $event->user, $event->user);
            }
        });
        Event::listen(TwoFactorAuthenticationChallenged::class, function ($event) {
            session(['login.version' => $event->user->auth_version]);
        });
        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function ($event) {
            session(['mfa.user_id' => $event->user->id]);
            AccessAudit::record('mfa.challenge_passed', $event->user, $event->user);
        });
        Livewire::addPersistentMiddleware([
            EnsureAccountIsActive::class,
            RequireAdministratorMfa::class,
            RequirePassword::class,
        ]);
    }
}
