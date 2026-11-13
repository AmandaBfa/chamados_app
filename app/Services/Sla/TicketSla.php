<?php

namespace App\Services\Sla;

use App\Services\TicketOperations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TicketSla
{
    public static function effectiveVersion(CarbonImmutable $at): int
    {
        return DB::table('sla_versions')->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')->orderByDesc('id')->value('id')
            ?? DB::table('sla_versions')->whereNotNull('effective_at')->orderBy('id')->value('id');
    }

    public function snapshot(object $ticket, CarbonImmutable $now): ?array
    {
        if (! ($ticket->sla_version_id ?? null)) {
            return null;
        }

        $now = $now->setMicrosecond(0);
        $version = DB::table('sla_versions')->find($ticket->sla_version_id);
        $calendar = new BusinessCalendar($version->timezone, json_decode($version->weekly_windows, true),
            json_decode($version->exceptions, true));
        $targets = json_decode($version->targets, true);
        $events = DB::table('ticket_events')->where('ticket_id', $ticket->id)
            ->where('occurred_at', '<=', $now)->orderBy('id')->get()
            ->map(fn ($event) => [$event, json_decode($event->details, true)]);
        $firstPriority = $events->first(fn ($entry) => isset($entry[1]['after']['priority']));
        $priority = $firstPriority[1]['after']['priority'] ?? $ticket->priority;
        $opened = CarbonImmutable::parse($ticket->created_at);
        $cursor = $opened;
        $cycle = 1;
        $waiting = false;
        $pausedSeconds = 0;
        $resolvedAt = null;
        $breaches = [];
        $revisions = [];
        $clocks = [
            'response' => $this->newClock($calendar, $opened, $targets[$priority][0] ?? null),
            'solution' => $this->newClock($calendar, $opened, $targets[$priority][1] ?? null),
        ];

        foreach ($events as [$event, $details]) {
            $at = CarbonImmutable::parse($event->occurred_at)->max($cursor);
            $after = $details['after'] ?? [];
            $nextWaiting = $details['solution_paused'] ?? (isset($after['status'])
                ? $after['status'] === 'aguardando_solicitante' : $waiting);
            $this->advance($calendar, $clocks, $breaches, $cycle, $waiting, $cursor, $at);
            if ($waiting) {
                $pausedSeconds += $calendar->secondsBetween($cursor, $at);
            }

            if (isset($after['priority'])) {
                $priority = $after['priority'];
                foreach (['response', 'solution'] as $index => $name) {
                    $clock = &$clocks[$name];
                    $initial = $event->id === $firstPriority[0]->id;
                    if (! $initial && $clock['end'] !== null) {
                        continue;
                    }
                    $previous = $initial ? null : $clock['due'];
                    if (! $initial) {
                        $this->setTarget($clock, $calendar, $at, $targets[$priority][$index], $name === 'solution' && $nextWaiting);
                        if ($clock['budget'] < $clock['used']) {
                            $breaches[$name === 'response' ? 1 : $cycle][$name] ??= $at;
                        }
                    }
                    $revisions[] = ['ticket_event_id' => $event->id, 'clock' => $name,
                        'previous_due_at' => $previous, 'new_due_at' => $clock['due']];
                    unset($clock);
                }
            }

            if ($cycle === 1 && isset($after['first_response_at']) && $clocks['response']['end'] === null) {
                $clocks['response']['end'] = 'respondido';
            }

            if (in_array($after['status'] ?? null, ['resolvido', 'cancelado'], true)) {
                $outcome = $after['status'];
                $clocks['response']['end'] ??= $outcome === 'resolvido' ? 'sem resposta' : $outcome;
                $clocks['solution']['end'] = $outcome;
                $resolvedAt = $outcome === 'resolvido' ? $at : null;
            }
            if ($event->event_type === 'ticket.reopen') {
                $cycle = (int) $after['cycle'];
                $clocks['solution']['end'] = null;
                $clocks['solution']['due'] = $this->due($calendar, $at, $clocks['solution']);
                if (isset($breaches[$cycle - 1]['solution'])) {
                    $breaches[$cycle]['solution'] = $at;
                }
                $resolvedAt = null;
            }

            $wasWaiting = $waiting;
            $waiting = $nextWaiting;
            if ($waiting) {
                $clocks['solution']['due'] = null;
            } elseif ($wasWaiting && $clocks['solution']['end'] === null) {
                $clocks['solution']['due'] = $this->due($calendar, $at, $clocks['solution']);
            }
            $cursor = $at;
        }

        $this->advance($calendar, $clocks, $breaches, $cycle, $waiting, $cursor, $now);
        if ($waiting) {
            $pausedSeconds += $calendar->secondsBetween($cursor, $now);
        }
        foreach ($clocks as &$clock) {
            $clock['remaining'] = $clock['budget'] === null ? null : $clock['budget'] - $clock['used'];
        }
        unset($clock);

        return [
            'clocks' => $clocks, 'breaches' => $breaches, 'revisions' => $revisions,
            'cycle' => $cycle, 'paused_seconds' => $pausedSeconds, 'paused' => $waiting,
            'age_seconds' => max(0, $now->timestamp - $opened->timestamp),
            'close_at' => $resolvedAt ? $calendar->addSeconds($resolvedAt, 27 * 3600) : null,
        ];
    }

    private function newClock(BusinessCalendar $calendar, CarbonImmutable $at, ?int $minutes): array
    {
        $clock = ['used' => 0, 'end' => null];
        $this->setTarget($clock, $calendar, $at, $minutes, false);

        return $clock;
    }

    private function setTarget(array &$clock, BusinessCalendar $calendar, CarbonImmutable $at, ?int $minutes, bool $paused): void
    {
        $clock['budget'] = $minutes === null ? null : $minutes * 60;
        $clock['due'] = $paused ? null : $this->due($calendar, $at, $clock);
    }

    private function due(BusinessCalendar $calendar, CarbonImmutable $at, array $clock): ?CarbonImmutable
    {
        return $clock['budget'] === null ? null : $calendar->addSeconds($at, max(0, $clock['budget'] - $clock['used']));
    }

    private function advance(BusinessCalendar $calendar, array &$clocks, array &$breaches, int $cycle, bool $waiting,
        CarbonImmutable $from, CarbonImmutable $until): void
    {
        $elapsed = $calendar->secondsBetween($from, $until);
        foreach ($clocks as $name => &$clock) {
            if ($clock['end'] !== null || ($waiting && $name === 'solution')) {
                continue;
            }
            $clock['used'] += $elapsed;
            if ($clock['due'] && $until->gt($clock['due'])) {
                $breaches[$name === 'response' ? 1 : $cycle][$name] ??= $clock['due'];
            }
        }
    }

    public function persist(object $ticket, CarbonImmutable $now): ?array
    {
        $snapshot = $this->snapshot($ticket, $now);
        foreach ($snapshot['breaches'] ?? [] as $cycle => $clocks) {
            foreach ($clocks as $clock => $instant) {
                DB::table('sla_breaches')->insertOrIgnore([
                    'ticket_id' => $ticket->id, 'cycle' => $cycle, 'clock' => $clock,
                    'breached_at' => $instant, 'detected_at' => $now,
                ]);
            }
        }
        if (! empty($snapshot['revisions'])) {
            DB::table('sla_deadline_revisions')->insertOrIgnore($snapshot['revisions']);
        }

        return $snapshot;
    }

    public function process(int $id, ?CarbonImmutable $now = null): void
    {
        DB::transaction(function () use ($id, $now) {
            $ticket = DB::table('tickets')->where('id', $id)->lockForUpdate()->first();
            if (! $ticket) {
                return;
            }
            $now ??= CarbonImmutable::now('UTC');
            $snapshot = $this->persist($ticket, $now);
            if ($ticket->status === 'resolvido' && $ticket->reopen_requested_at === null
                && ($snapshot['close_at'] ?? null) && $now->gte($snapshot['close_at'])) {
                TicketOperations::record($ticket, null, 'ticket.auto_closed', ['status' => 'fechado'],
                    ['eligible_at' => $snapshot['close_at']], 'Chamado fechado automaticamente após 27 horas de cobertura.', $now);
            }
        }, 3);
    }
}
