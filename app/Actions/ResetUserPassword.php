<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AccessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]])->validate();
        DB::transaction(function () use ($user, $input) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if (! $locked->canLoginLocally()) {
                throw ValidationException::withMessages(['email' => 'Não foi possível redefinir a senha desta conta.']);
            }
            $locked->forceFill(['password' => $input['password'], 'auth_version' => $locked->auth_version + 1])->save();
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            AccessAudit::record('password.reset', $locked, $locked);
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
