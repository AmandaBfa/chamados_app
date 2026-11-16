<?php

namespace App\Services;

use App\Services\Sla\TicketSla;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TicketAging
{
    public function __construct(private TicketSla $sla) {}

    // O chamador mantém a transação aberta até terminar as consultas da requisição.
    public function project(Builder $tickets, CarbonImmutable $now): void
    {
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS ticket_aging_request (
            ticket_id bigint PRIMARY KEY, age bigint, business bigint, inactive bigint,
            movement timestamptz, response_overdue boolean, solution_overdue boolean, balance bigint
        ) ON COMMIT DROP');
        DB::statement('TRUNCATE pg_temp.ticket_aging_request');
        $calendars = [];
        $tickets->select('tickets.*')->addSelect(['last_movement' => TicketActivity::lastMovement()])
            ->chunkById(100, function ($batch) use ($now, &$calendars) {
                $rows = [];
                foreach ($batch as $ticket) {
                    $opened = CarbonImmutable::parse($ticket->created_at);
                    $movement = CarbonImmutable::parse($ticket->last_movement ?? $ticket->created_at);
                    $version = $ticket->sla_version_id;
                    $calendar = $version ? ($calendars[$version] ??= $this->sla->calendar($version)) : null;
                    $snapshot = in_array($ticket->status, TicketActivity::ACTIVE, true)
                        ? $this->sla->snapshot($ticket, $now) : null;
                    $response = $snapshot['clocks']['response'] ?? null;
                    $solution = $snapshot['clocks']['solution'] ?? null;
                    $responseActive = $response && $response['end'] === null && $response['budget'] !== null;
                    $solutionActive = $solution && $solution['end'] === null && $solution['budget'] !== null && ! $snapshot['paused'];
                    $balances = array_filter([
                        $responseActive ? $response['remaining'] : null,
                        $solutionActive ? $solution['remaining'] : null,
                    ], fn ($value) => $value !== null);
                    $rows[] = [
                        'ticket_id' => $ticket->id,
                        'age' => max(0, $now->timestamp - $opened->timestamp),
                        'business' => $calendar?->secondsBetween($opened, $now),
                        'inactive' => $calendar?->secondsBetween($movement, $now),
                        'movement' => $movement->utc(),
                        'response_overdue' => $responseActive && isset($snapshot['breaches'][1]['response']),
                        'solution_overdue' => $solutionActive && isset($snapshot['breaches'][$ticket->cycle]['solution']),
                        'balance' => $balances === [] ? null : min($balances),
                    ];
                }
                DB::table('pg_temp.ticket_aging_request')->insert($rows);
            }, 'tickets.id', 'id');
    }

    public function order(Builder $query): Builder
    {
        return $query->orderByRaw("(tickets.priority IS NOT DISTINCT FROM 'P1') DESC")
            ->orderByRaw('(aging.response_overdue OR aging.solution_overdue) DESC NULLS LAST')
            ->orderByRaw('aging.balance ASC NULLS LAST')
            ->orderBy('tickets.created_at')->orderBy('tickets.id');
    }
}
