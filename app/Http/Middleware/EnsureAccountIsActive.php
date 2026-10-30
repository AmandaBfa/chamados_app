<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $user->refresh();
            if (! $user->canLoginLocally() || (int) $request->session()->get('auth.version', -1) !== $user->auth_version) {
                return $this->reject($request);
            }
        }
        if ($pendingId = $request->session()->get('login.id')) {
            $pending = User::find($pendingId);
            if (! $pending?->canLoginLocally() ||
                (int) $request->session()->get('login.version', -1) !== $pending->auth_version) {
                return $this->reject($request);
            }
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function reject(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Sua sessão foi encerrada. Entre novamente.'], 401)
            : redirect()->route('login')->withErrors(['email' => 'Sua sessão foi encerrada. Entre novamente.']);
    }
}
