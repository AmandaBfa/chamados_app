<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\AccessAudit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->find($token);

        return view('auth.invitation', ['token' => $token, 'invitedName' => $invitation->user->name]);
    }

    private function find(string $token): Invitation
    {
        abort_unless(strlen($token) === 64, 404);
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->first();
        abort_unless($invitation && ! $invitation->consumed_at && $invitation->expires_at->isFuture() &&
            $invitation->user->active && $invitation->user->password === null, 410, 'Convite inválido, expirado ou já utilizado. Solicite um novo convite.');

        return $invitation;
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        DB::transaction(function () use ($token, $data) {
            // Mesma ordem de bloqueio de reenvio: usuário antes do convite.
            $found = $this->find($token);
            $user = User::lockForUpdate()->findOrFail($found->user_id);
            $invitation = Invitation::lockForUpdate()->findOrFail($found->id);
            abort_unless($user->active && $user->password === null && ! $invitation->consumed_at && $invitation->expires_at->isFuture(), 410, 'Convite indisponível.');
            $user->forceFill(['password' => $data['password'], 'email_verified_at' => now()])->save();
            $invitation->update(['consumed_at' => now()]);
            AccessAudit::record('invitation.accepted', $user, $user);
            event(new Verified($user));
        });

        return redirect()->route('login')->with('status', 'Convite aceito e e-mail confirmado. Entre com sua senha.');
    }
}
