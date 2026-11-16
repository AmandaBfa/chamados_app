<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TicketActivity
{
    public const ACTIVE = ['novo', 'em_triagem', 'em_atendimento', 'aguardando_solicitante', 'aguardando_terceiro'];

    public static function lastMovement(): Builder
    {
        return DB::table('ticket_events as activity')->whereColumn('activity.ticket_id', 'tickets.id')
            ->where(function (Builder $query) {
                $query->whereIn('activity.event_type', [
                    'ticket.opened', 'comment.added', 'ticket.reply', 'ticket.note',
                    'ticket.requester_returned', 'ticket.reopen_requested', 'ticket.decline_reopen',
                    'ticket.assign', 'ticket.forward', 'ticket.claim', 'ticket.take_triage', 'ticket.collaborate',
                ])->orWhereRaw("jsonb_exists(activity.details->'after', 'status')");
            })
            ->orderByDesc('activity.occurred_at')->orderByDesc('activity.id')->limit(1)
            ->select('activity.occurred_at');
    }
}
