<?php

namespace App\Services\Alerts;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\TicketOperations;
use Illuminate\Support\Facades\DB;

final class AlertRecipients
{
    public function eligible(User $user, int $ticket): bool
    {
        return $user->canLoginLocally() && $user->hasVerifiedEmail()
            && app(TicketOperations::class)->readableBy($user)->where('tickets.id', $ticket)->exists();
    }

    public function resolve(object $ticket, string $audience): array
    {
        $teams = DB::table('teams')->where('active', true);
        $ids = match ($audience) {
            'owner' => [$ticket->assignee_id],
            'manager' => DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.name', RoleName::Manager->value)->pluck('user_id')->all(),
            'team' => $teams->where('id', $ticket->team_id)->pluck('reference_user_id')->all(),
            'triage' => $teams->where('triage_coverage', true)->orderBy('id')
                ->get(['reference_user_id', 'substitute_user_id'])->flatMap(fn ($team) => [
                    $team->reference_user_id, $team->substitute_user_id,
                ])->all(),
            'specialist' => $this->specialists($ticket, $teams),
        };

        return User::query()->whereIn('id', array_filter($ids))->where('active', true)->get()
            ->filter(fn (User $user) => $this->eligible($user, $ticket->id))->modelKeys();
    }

    private function specialists(object $ticket, $teams): array
    {
        $service = DB::table('services')->where('active', true)->find($ticket->service_id);
        $references = $teams->where(fn ($query) => $query->where('critical_alternative', true)
            ->orWhere('id', $ticket->team_id))->pluck('reference_user_id')->all();

        return [$service?->reference_user_id, ...$references];
    }
}
