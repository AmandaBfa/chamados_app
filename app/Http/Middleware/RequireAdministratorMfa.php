<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\Request;

class RequireAdministratorMfa
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->hasRole(RoleName::Administrator) &&
            (! $user->hasEnabledTwoFactorAuthentication() || $request->session()->get('mfa.user_id') !== $user->id)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Configure e confirme o segundo fator para continuar.'], 403)
                : redirect()->route('security.show');
        }

        return $next($request);
    }
}
