<?php

namespace App\Services\Alerts;

use App\Jobs\DeliverTicketAlert;
use App\Services\Sla\TicketSla;
use App\Services\TicketActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final class TicketAlerts
{
    public const LABELS = [
        'critical' => 'Chamado crítico',
        'suspected_critical' => 'Suspeita crítica na abertura',
        'unassigned' => 'Chamado sem responsável elegível',
        'warning70' => 'Aviso preventivo de SLA',
        'warning90' => 'Revisar prazo de solução',
        'breach' => 'Prazo vencido',
        'stale' => 'Chamado parado em tempo útil',
        'resumed' => 'Solicitante respondeu e retomou o atendimento',
    ];

    public function __construct(private TicketSla $sla, private AlertRecipients $recipients) {}

    public function process(int $id, CarbonImmutable $now): void
    {
        DB::transaction(function () use ($id, $now) {
            $ticket = DB::table('tickets')->where('id', $id)->whereIn('status', TicketActivity::ACTIVE)
                ->addSelect('tickets.*')->addSelect(['last_movement' => TicketActivity::lastMovement()])
                ->lockForUpdate()->first();
            if (! $ticket || ! $ticket->sla_version_id) {
                return;
            }

            $calendar = $this->sla->calendar($ticket->sla_version_id);
            if (! $calendar->covers($now)) {
                return;
            }

            $snapshot = $this->sla->snapshot($ticket, $now);
            $owner = $this->recipients->resolve($ticket, 'owner');
            $age = $calendar->secondsBetween(CarbonImmutable::parse($ticket->created_at), $now);
            $limit = config('ticket_alerts.unassigned_minutes.'.($ticket->priority ?? 'unclassified'));
            $movement = CarbonImmutable::parse($ticket->last_movement ?? $ticket->created_at);
            $events = [];
            if ($ticket->priority === 'P1') {
                $events[] = ['operation', 'critical', ['triage', 'specialist', 'manager']];
            }
            if ($ticket->priority === null && $ticket->cycle === 1 && $ticket->work_condition === 'impedido'
                && in_array($ticket->impact_scope, config('ticket_alerts.critical_suspicion_scopes'), true)) {
                $events[] = ['operation', 'suspected_critical', ['triage', 'specialist', 'manager']];
            }
            if ($owner === [] && $age >= $limit * 60) {
                $events[] = ['operation', 'unassigned', ['triage']];
            }
            if (in_array($ticket->priority, ['P3', 'P4'], true)
                && $calendar->secondsBetween($movement, $now) >= config('ticket_alerts.stale_minutes') * 60) {
                $events[] = ['operation', 'stale', [$owner === [] ? 'triage' : 'owner']];
            }
            if ($ticket->status === 'em_atendimento' && DB::table('ticket_events')
                ->where('ticket_id', $id)->whereRaw("details->>'cycle' = ?", [(string) $ticket->cycle])
                ->where('occurred_at', '>=', $now->subMinutes(config('ticket_alerts.resume_lookback_minutes')))
                ->where('event_type', 'ticket.requester_returned')->exists()) {
                $events[] = ['operation', 'resumed', ['owner']];
            }

            foreach ($snapshot['clocks'] as $clock => $value) {
                if ($value['end'] !== null || ! $value['budget'] || ($clock === 'solution' && $snapshot['paused'])) {
                    continue;
                }
                $cycle = $clock === 'response' ? 1 : $ticket->cycle;
                $percent = 100 * $value['used'] / $value['budget'];
                if (isset($snapshot['breaches'][$cycle][$clock])) {
                    $events[] = [$clock, 'breach', ['owner', 'manager']];
                } elseif ($clock === 'solution' && $percent >= 90) {
                    $events[] = [$clock, 'warning90', ['owner', 'team']];
                } elseif ($percent >= ($clock === 'response' ? config('ticket_alerts.response_warning_percent') : 70)) {
                    $events[] = [$clock, 'warning70', ['owner']];
                }
            }

            foreach ($events as [$clock, $milestone, $audiences]) {
                foreach ($audiences as $audience) {
                    $recipients = $audience === 'owner' ? $owner : $this->recipients->resolve($ticket, $audience);
                    $key = ['ticket_id' => $id, 'cycle' => $clock === 'response' ? 1 : $ticket->cycle,
                        'clock' => $clock, 'milestone' => $milestone, 'audience' => $audience,
                        'detected_at' => $now, 'updated_at' => $now];
                    if ($recipients === []) {
                        DB::table('ticket_alerts')->insertOrIgnore($key + [
                            'recipient_id' => null, 'channel' => 'audit', 'status' => 'missing',
                        ]);
                    }
                    foreach ($recipients as $recipient) {
                        $this->enqueue($key, $recipient, 'inbox');
                        if (config('ticket_alerts.local_mail')) {
                            $this->enqueue($key, $recipient, 'mail');
                        }
                    }
                }
            }
        }, 3);
    }

    private function enqueue(array $key, int $recipient, string $channel): void
    {
        $row = DB::selectOne('INSERT INTO ticket_alerts
            (ticket_id, cycle, clock, milestone, audience, detected_at, updated_at, recipient_id, channel, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT DO NOTHING RETURNING id',
            [...array_values($key), $recipient, $channel, 'queued']);
        if ($row) {
            Bus::dispatch((new DeliverTicketAlert($row->id))->onConnection('ticket-alerts')->beforeCommit());
        }
    }
}
