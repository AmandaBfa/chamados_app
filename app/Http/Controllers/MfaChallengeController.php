<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class MfaChallengeController extends TwoFactorAuthenticatedSessionController
{
    public function store(TwoFactorLoginRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->session()->get('login.id'))->lockForUpdate()->first();
            abort_unless($user?->canLoginLocally() &&
                $user->auth_version === (int) $request->session()->get('login.version', -1), 403);

            // Serializa o consumo dos códigos de recuperação do mesmo usuário.
            return parent::store($request);
        });
    }
}
