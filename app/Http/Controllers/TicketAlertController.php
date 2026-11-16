<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Services\Alerts\TicketAlerts;
use App\Services\TicketOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TicketAlertController extends Controller
{
    public function __invoke(Request $request, TicketOperations $tickets)
    {
        $actor = $request->user();
        $visible = $tickets->queue($actor)->select('tickets.id');
        $alerts = DB::table('ticket_alerts')->join('tickets', 'tickets.id', '=', 'ticket_alerts.ticket_id')
            ->where('recipient_id', $actor->id)->where('channel', 'inbox')->where('ticket_alerts.status', 'sent')
            ->whereIn('ticket_id', clone $visible)->orderByDesc('ticket_alerts.id')
            ->paginate(20, ['ticket_alerts.*', 'tickets.protocol']);
        $issues = null;
        if ($actor->hasRole(RoleName::Manager)) {
            $issues = DB::table('ticket_alerts')->join('tickets', 'tickets.id', '=', 'ticket_alerts.ticket_id')
                ->whereIn('ticket_id', $visible)->whereIn('ticket_alerts.status', ['missing', 'failed'])
                ->orderByDesc('ticket_alerts.id')->paginate(20, ['ticket_alerts.*', 'tickets.protocol'], 'falhas');
        }

        return view('ti.alerts', ['alerts' => $alerts, 'issues' => $issues, 'labels' => TicketAlerts::LABELS]);
    }
}
