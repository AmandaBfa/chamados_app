<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PortalAccess
{
    public function authorize(User $actor): void
    {
        $actor->refresh();
        abort_unless($actor->canLoginLocally() && $actor->hasVerifiedEmail() &&
            (int) session('auth.version', -1) === $actor->auth_version, 403);
        if ($actor->hasRole(RoleName::Administrator)) {
            abort_unless($actor->hasEnabledTwoFactorAuthentication() && session('mfa.user_id') === $actor->id, 403);
        }
    }

    public function own(User $actor, int $id): object
    {
        $this->authorize($actor);

        $ticket = DB::table('tickets')->where('requester_id', $actor->id)->where('id', $id)->first();
        abort_unless($ticket, 404);

        return $ticket;
    }
}
