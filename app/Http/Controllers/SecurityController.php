<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Services\AccessAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class SecurityController extends Controller
{
    private function user(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole(RoleName::Administrator) || $user->hasEnabledTwoFactorAuthentication(), 403);
        if ($user->hasEnabledTwoFactorAuthentication()) {
            abort_unless($request->session()->get('mfa.user_id') === $user->id, 403, 'Entre novamente e confirme seu segundo fator.');
        }

        return $user;
    }

    public function show(Request $request)
    {
        $user = $this->user($request);

        return view('auth.security', ['securityUser' => $user]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable)
    {
        $user = $this->user($request);
        abort_if($user->hasEnabledTwoFactorAuthentication(), 403);
        DB::transaction(function () use ($user, $enable) {
            $enable($user);
            AccessAudit::record('mfa.enrollment_started', $user, $user);
        });

        return redirect()->route('security.show');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm)
    {
        $user = $this->user($request);
        abort_if($user->hasEnabledTwoFactorAuthentication(), 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        DB::transaction(function () use ($user, $data, $confirm) {
            $confirm($user, $data['code']);
            AccessAudit::record('mfa.enrollment_confirmed', $user, $user);
        });
        $request->session()->put('mfa.user_id', $user->id);
        $request->session()->regenerate();

        return redirect()->route('security.show')->with('recovery_codes', $user->recoveryCodes())
            ->with('status', 'Segundo fator confirmado. Guarde os códigos de recuperação em local seguro.');
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate)
    {
        $user = $this->user($request);
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);
        DB::transaction(function () use ($user, $generate) {
            $generate($user);
            AccessAudit::record('mfa.recovery_codes_regenerated', $user, $user);
        });

        return redirect()->route('security.show')->with('recovery_codes', $user->recoveryCodes())
            ->with('status', 'Novos códigos gerados. Os anteriores foram invalidados.');
    }
}
