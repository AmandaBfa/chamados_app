<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Services\TicketActivity;
use App\Services\TicketAging;
use App\Services\TicketOperations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TicketDashboardController extends Controller
{
    private const DIMENSIONS = [
        'unit_id' => ['Unidade', 'units'], 'department_id' => ['Setor', 'departments'],
        'category_id' => ['Categoria', 'categories'], 'service_id' => ['Serviço', 'services'],
        'team_id' => ['Equipe', 'teams'], 'assignee_id' => ['Responsável', 'users'],
    ];

    public function __invoke(Request $request, TicketOperations $operations, TicketAging $aging)
    {
        $actor = $request->user();
        $visible = $operations->queue($actor);
        abort_unless($actor->hasRole(RoleName::Manager), 403);
        $tabs = ['operacao' => 'Operação', 'desempenho' => 'Desempenho', 'recorrencias' => 'Recorrências'];
        $labels = [
            'open' => 'Abertos agora', 'entries' => 'Entradas no período', 'canceled' => 'Cancelamentos no período',
            'late_response' => 'Abertos com resposta vencida', 'late_solution' => 'Abertos com solução vencida',
            'cycles' => 'Ciclos resolvidos', 'resolved' => 'Chamados distintos resolvidos',
            'responses' => 'Primeiras respostas elegíveis', 'responses_met' => 'Primeiras respostas dentro da meta',
            'solutions' => 'Ciclos com SLA de solução', 'solutions_met' => 'Ciclos dentro da meta de solução',
            'no_response_sla' => 'Primeiras respostas sem meta', 'no_solution_sla' => 'Ciclos resolvidos sem meta',
            'reopened' => 'Eventos de reabertura', 'recurrence' => 'Chamados do grupo',
            'reopen_cohort' => 'Chamados na coorte de reabertura',
            'reopen_within' => 'Chamados reabertos dentro da janela',
            'response_times' => 'Tempo corrido até a primeira resposta',
            'solution_times' => 'Tempo corrido até a resolução do ciclo',
        ];
        $priorities = array_combine(['P1', 'P2', 'P3', 'P4'], ['P1', 'P2', 'P3', 'P4']);
        $dimensions = ['category_id' => ['Categoria', 'categories'], 'service_id' => ['Serviço', 'services'],
            'equipment_reference_id' => ['Equipamento', 'equipment_references']];
        $data = $request->validate([
            'aba' => ['sometimes', Rule::in(array_keys($tabs))],
            'indicador' => ['sometimes', Rule::in(array_keys($labels))],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(array_keys($priorities))],
            'status' => ['nullable', Rule::in(array_keys(TicketOperations::STATES))],
            'agrupar' => ['sometimes', Rule::in(array_keys($dimensions))],
            'grupo' => ['required_if:indicador,recurrence', 'nullable', 'integer', 'min:0'],
            ...array_fill_keys(array_keys(self::DIMENSIONS), ['nullable', 'integer', 'min:1']),
        ]);

        $zone = config('installation.display_timezone');
        $now = CarbonImmutable::now('UTC')->setMicrosecond(0);
        $local = $now->setTimezone($zone);
        $from = CarbonImmutable::parse($data['inicio'] ?? $local->startOfMonth()->toDateString(), $zone)->startOfDay();
        $until = CarbonImmutable::parse($data['fim'] ?? $local->toDateString(), $zone)->startOfDay()->addDay();
        if ($from->gte($until)) {
            throw ValidationException::withMessages(['fim' => 'Informe um fim igual ou posterior ao início.']);
        }
        $start = $from->utc();
        $end = $until->utc();
        $tab = $data['aba'] ?? 'operacao';
        $indicator = $data['indicador'] ?? null;
        $dimension = $data['agrupar'] ?? 'category_id';
        $links = array_filter($data, fn ($value) => $value !== null);
        unset($links['indicador'], $links['grupo']);
        $links['inicio'] = $from->toDateString();
        $links['fim'] = $until->subDay()->toDateString();
        $links['aba'] = $tab;

        $choices = [];
        foreach (self::DIMENSIONS as $field => [$label, $table]) {
            $choices[$field] = [$label, DB::table($table)->whereIn('id', (clone $visible)->select($field))
                ->orderBy('name')->pluck('name', 'id')];
        }
        $cities = (clone $visible)->whereRaw("tickets.opening_context->'units'->>'city' IS NOT NULL")
            ->selectRaw("tickets.opening_context->'units'->>'city' AS city")->distinct()->orderBy('city')->pluck('city');
        $choices += ['cidade' => ['Cidade na abertura', $cities->mapWithKeys(fn ($city) => [$city => $city])],
            'priority' => ['Prioridade atual', $priorities], 'status' => ['Situação atual', TicketOperations::STATES]];
        foreach ($choices as $field => $_) {
            if (isset($data[$field])) {
                if ($field === 'cidade') {
                    $visible->whereRaw("tickets.opening_context->'units'->>'city' = ?", [$data[$field]]);
                } else {
                    $visible->where('tickets.'.$field, $data[$field]);
                }
            }
        }

        $open = (clone $visible)->whereIn('tickets.status', TicketActivity::ACTIVE);
        $entries = (clone $visible)->where('tickets.created_at', '>=', $start)->where('tickets.created_at', '<', $end)
            ->select('tickets.*')->addSelect('tickets.created_at as metric_at');
        $events = (clone $visible)->join('ticket_events as metric_event', 'metric_event.ticket_id', '=', 'tickets.id')
            ->where('metric_event.occurred_at', '>=', $start)->where('metric_event.occurred_at', '<', $end)
            ->select('tickets.*', 'metric_event.occurred_at as metric_at', 'metric_event.id as metric_event_id')
            ->selectRaw("coalesce((metric_event.details->>'cycle')::int, 1) as metric_cycle");
        $cycles = (clone $events)->where('metric_event.event_type', 'ticket.resolve');

        $responses = (clone $events)->whereRaw("jsonb_exists(metric_event.details->'after', 'first_response_at')")
            ->whereRaw("coalesce((metric_event.details->>'cycle')::int, 1) = 1")
            ->whereRaw("metric_event.details->'after'->>'first_response_at' IS NOT NULL");
        $responseSla = (clone $responses)->whereExists($this->hasTarget());
        $solutionSla = (clone $cycles)->whereExists($this->hasTarget());

        $windowDays = max(1, (int) config('tickets.reporting.reopen_window_days'));
        $windowSeconds = $windowDays * 86400;
        $cohort = (clone $cycles)->where('metric_event.occurred_at', '<=', $now->subSeconds($windowSeconds))
            ->whereNotExists(DB::table('ticket_events as previous')->selectRaw('1')
                ->whereColumn('previous.ticket_id', 'tickets.id')->where('previous.event_type', 'ticket.resolve')
                ->whereRaw('(previous.occurred_at, previous.id) < (metric_event.occurred_at, metric_event.id)'));
        $reopenedWithin = (clone $cohort)->whereExists(DB::table('ticket_events as reopened')->selectRaw('1')
            ->whereColumn('reopened.ticket_id', 'tickets.id')->where('reopened.event_type', 'ticket.reopen')
            ->whereRaw('(reopened.occurred_at, reopened.id) > (metric_event.occurred_at, metric_event.id)')
            ->whereRaw("reopened.occurred_at <= metric_event.occurred_at + (? * interval '1 second')", [$windowSeconds]));
        $responseTimes = (clone $responses)->selectRaw('extract(epoch from metric_event.occurred_at - tickets.created_at) / 60.0 as minutes');
        $solutionTimes = (clone $cycles)->selectRaw("extract(epoch from metric_event.occurred_at - coalesce(
            (select r.occurred_at from ticket_events r where r.ticket_id = tickets.id and r.event_type = 'ticket.reopen'
                and r.id < metric_event.id order by r.id desc limit 1), tickets.created_at)) / 60.0 as minutes");
        $percentiles = config('tickets.reporting.time_percentiles');

        $populations = [
            'reopen_cohort' => $cohort, 'reopen_within' => $reopenedWithin,
            'response_times' => $responseTimes, 'solution_times' => $solutionTimes,
            'open' => $open, 'entries' => $entries,
            'canceled' => (clone $events)->where('metric_event.event_type', 'ticket.cancel')
                ->selectRaw("metric_event.details->>'reason' as cancellation_reason"),
            'cycles' => $cycles,
            'resolved' => (clone $visible)->whereIn('tickets.id', (clone $cycles)->select('tickets.id')),
            'responses' => $responseSla,
            'responses_met' => (clone $responseSla)->whereNotExists($this->breach('response')),
            'solutions' => $solutionSla,
            'solutions_met' => (clone $solutionSla)->whereNotExists($this->breach('solution')),
            'no_response_sla' => (clone $responses)->whereNotExists($this->hasTarget()),
            'no_solution_sla' => (clone $cycles)->whereNotExists($this->hasTarget()),
            'reopened' => (clone $events)->where('metric_event.event_type', 'ticket.reopen'),
        ];
        if ($indicator === 'recurrence') {
            $recurrence = clone $entries;
            $group = (int) $data['grupo'];
            $group === 0 ? $recurrence->whereNull('tickets.'.$dimension) : $recurrence->where('tickets.'.$dimension, $group);
            $populations['recurrence'] = $recurrence;
        }

        $metrics = match ($tab) {
            'operacao' => ['open', 'entries', 'canceled', 'late_response', 'late_solution'],
            'desempenho' => ['cycles', 'resolved', 'responses', 'responses_met', 'solutions', 'solutions_met',
                'no_response_sla', 'no_solution_sla', 'reopened', 'reopen_cohort', 'reopen_within', 'late_response', 'late_solution'],
            default => [],
        };
        [$counts, $details, $groups, $times] = DB::transaction(function () use ($percentiles, $aging, $now, $open, $populations, $metrics, $indicator, $entries, $dimension, $dimensions, $tab) {
            if ($tab !== 'recorrencias' || in_array($indicator, ['late_response', 'late_solution'], true)) {
                $aging->project(clone $open, $now);
                foreach (['response', 'solution'] as $clock) {
                    $populations['late_'.$clock] = (clone $open)->join('pg_temp.ticket_aging_request as aging', 'aging.ticket_id', '=', 'tickets.id')
                        ->where('aging.'.$clock.'_overdue', true)->select('tickets.*');
                }
            }
            $counts = [];
            foreach ($metrics as $metric) {
                $counts[$metric] = $populations[$metric]->count();
            }
            $details = $indicator ? $populations[$indicator]->orderByDesc('tickets.id')
                ->when(in_array($indicator, ['canceled', 'cycles', 'responses', 'responses_met', 'solutions',
                    'solutions_met', 'no_response_sla', 'no_solution_sla', 'reopened', 'reopen_cohort', 'reopen_within', 'response_times', 'solution_times'], true),
                    fn ($query) => $query->orderByDesc('metric_event.id'))
                ->paginate(20)->withQueryString() : null;
            $groups = $tab === 'recorrencias' ? (clone $entries)->leftJoin($dimensions[$dimension][1].' as dimension', 'dimension.id', '=', 'tickets.'.$dimension)
                ->select('dimension.id', 'dimension.name')->selectRaw('count(*) as total')
                ->groupBy('dimension.id', 'dimension.name')->orderByDesc('total')->orderBy('dimension.id')
                ->paginate(20, ['*'], 'grupos')->withQueryString() : null;

            $times = [];
            if ($tab === 'desempenho') {
                foreach (['response_times', 'solution_times'] as $key) {
                    $population = DB::query()->fromSub($populations[$key], 'durations');
                    $times[$key] = ['total' => $population->count(), 'values' => []];
                    foreach ($percentiles as $percentile) {
                        $times[$key]['values'][$percentile] = (clone $population)
                            ->selectRaw('percentile_cont(?) within group (order by minutes) as value', [$percentile / 100])->first()->value;
                    }
                }
            }

            return [$counts, $details, $groups, $times];
        });

        return view('ti.dashboard', compact('tab', 'tabs', 'labels', 'counts', 'choices', 'dimensions',
            'dimension', 'indicator', 'details', 'groups', 'links', 'data', 'zone', 'times', 'windowDays'));
    }

    private function hasTarget(): Builder
    {
        // A classificação inicial é aplicada desde a abertura pelo motor de SLA.
        return DB::table('ticket_events as classification')->selectRaw('1')
            ->whereColumn('classification.ticket_id', 'tickets.id')
            ->whereRaw("classification.details->'after'->>'priority' IN ('P1','P2','P3','P4')")
            ->whereNotNull('tickets.sla_version_id');
    }

    private function breach(string $clock): Builder
    {
        return DB::table('sla_breaches')->selectRaw('1')->whereColumn('sla_breaches.ticket_id', 'tickets.id')
            ->where('clock', $clock)->whereColumn('breached_at', '<=', 'metric_event.occurred_at')
            ->whereRaw("sla_breaches.cycle = coalesce((metric_event.details->>'cycle')::int, 1)");
    }
}
