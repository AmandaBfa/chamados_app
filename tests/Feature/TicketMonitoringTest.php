<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Ti\Queue;
use App\Models\Role;
use App\Models\User;
use App\Services\Alerts\TicketAlerts;
use App\Services\RequesterTickets;
use App\Services\TicketOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class TicketMonitoringTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginDedicatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username, host(inet_server_addr()) AS host');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $this->assertContains($identity->host, ['127.0.0.1', '::1']);
        $this->beginDedicatedTransaction();
    }

    public function test_alert_milestones_and_real_database_jobs_are_deduplicated_with_failure_and_revocation(): void
    {
        config(['ticket_alerts.local_mail' => true]);
        $owner = $this->login(RoleName::Support);
        $manager = $this->login(RoleName::Manager);
        $ticket = $this->open();
        $this->setTime('2026-11-13 08:00');
        $this->record($ticket, $owner, 'classify', ['priority' => 'P1', 'assignee_id' => $owner->id]);
        $this->record($ticket, $owner, 'reply', ['first_response_at' => now()]);
        $alerts = app(TicketAlerts::class);
        $deliveries = DB::table('ticket_alerts')->where('ticket_id', $ticket->id);

        $alerts->process($ticket->id, $this->setTime('2026-11-13 10:48'));
        $alerts->process($ticket->id, CarbonImmutable::now());
        $this->assertSame(2, (clone $deliveries)->where('milestone', 'warning70')->count());
        $this->assertSame(2, (clone $deliveries)->where('status', 'missing')->count());
        $this->assertSame(4, DB::table('jobs')->where('queue', 'ticket-alerts')->count());
        $this->workAvailableJobs();
        $this->assertSame(4, (clone $deliveries)->where('status', 'sent')->count());
        $this->assertSame(2, Mail::mailer('array')->getSymfonyTransport()->messages()->count());
        $body = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString($ticket->protocol, $body);
        $this->assertStringContainsString(route('ti.show', $ticket->id), $body);
        $this->assertStringNotContainsString($ticket->subject, $body);

        $alerts->process($ticket->id, $this->setTime('2026-11-13 11:36'));
        $this->assertSame(2, (clone $deliveries)->where('milestone', 'warning90')->whereNotNull('recipient_id')->count());
        $owner->forceFill(['active' => false])->save();
        $this->workAvailableJobs();
        $this->assertSame(2, (clone $deliveries)->where('milestone', 'warning90')->where('status', 'skipped')->count());
        $this->assertSame(2, Mail::mailer('array')->getSymfonyTransport()->messages()->count());

        $alerts->process($ticket->id, $this->setTime('2026-11-13 12:01'));
        $failed = (clone $deliveries)->where('milestone', 'breach')->where('channel', 'mail')
            ->where('recipient_id', $manager->id)->firstOrFail();
        config(['ticket_alerts.local_mail' => false]);
        foreach (['12:01', '12:02', '12:05'] as $time) {
            $this->setTime('2026-11-13 '.$time);
            $this->workAvailableJobs();
        }
        $this->assertDatabaseHas('ticket_alerts', ['id' => $failed->id, 'attempts' => 3, 'status' => 'failed']);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->asUser($manager);
        $this->get(route('ti.alerts'))->assertOk()->assertSee($ticket->protocol)->assertSee('Falha após 3 tentativas');
    }

    public function test_pilot_suspects_blocked_team_without_assigning_priority(): void
    {
        $manager = $this->login(RoleName::Manager);
        $ticket = $this->open();
        $isolated = $this->open();
        DB::table('tickets')->where('id', $isolated->id)->update(['impact_scope' => 'pessoa']);
        $alerts = app(TicketAlerts::class);
        $alerts->process($ticket->id, CarbonImmutable::now());
        $alerts->process($ticket->id, CarbonImmutable::now());
        $alerts->process($isolated->id, CarbonImmutable::now());
        $this->assertSame(1, DB::table('ticket_alerts')->where('milestone', 'suspected_critical')
            ->where('recipient_id', $manager->id)->where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseMissing('ticket_alerts', ['ticket_id' => $isolated->id, 'milestone' => 'suspected_critical']);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'priority' => null]);
    }

    public function test_alerts_wait_for_coverage_and_emit_only_highest_current_threshold_after_pause(): void
    {
        $owner = $this->login(RoleName::Support);
        $ticket = $this->open();
        $this->record($ticket, $owner, 'classify', ['priority' => 'P2', 'assignee_id' => $owner->id]);
        $this->record($ticket, $owner, 'reply', ['first_response_at' => now()]);
        $this->setTime('2026-11-13 16:15');
        $this->record($ticket, $owner, 'wait_requester', ['status' => 'aguardando_solicitante']);
        DB::table('sla_versions')->where('id', $ticket->sla_version_id)->update([
            'exceptions' => json_encode(['2026-11-16' => []]),
        ]);

        $alerts = app(TicketAlerts::class);
        $deliveries = DB::table('ticket_alerts')->where('ticket_id', $ticket->id);
        $alerts->process($ticket->id, $this->setTime('2026-11-16 12:00'));
        $alerts->process($ticket->id, $this->setTime('2026-11-17 08:00'));
        $this->assertSame(0, (clone $deliveries)->count());

        $this->record($ticket, $owner, 'requester_returned', ['status' => 'em_atendimento']);
        $alerts->process($ticket->id, CarbonImmutable::now());
        $this->assertSame(1, (clone $deliveries)->where('milestone', 'warning90')->where('recipient_id', $owner->id)->count());
        $this->assertSame(0, (clone $deliveries)->where('milestone', 'warning70')->count());

        $this->setTime('2026-11-17 20:00');
        $this->workAvailableJobs();
        $this->assertSame(0, (clone $deliveries)->where('status', 'sent')->count());
        $this->setTime('2026-11-18 08:01');
        $this->workAvailableJobs();
        $this->assertSame(2, (clone $deliveries)->where('status', 'sent')->count());
    }

    public function test_queue_uses_calendar_and_significant_movements_without_changing_priority(): void
    {
        $staff = $this->login(RoleName::Support);
        $ticket = $this->open();
        $this->record($ticket, $staff, 'classify', ['priority' => 'P4']);
        $this->setTime('2026-11-13 16:50');
        $this->record($ticket, $staff, 'note', [], ['comment_id' => 123]);
        DB::table('sla_versions')->where('id', $ticket->sla_version_id)->update([
            'exceptions' => json_encode(['2026-11-16' => []]),
        ]);
        $this->asUser($staff);
        $this->setTime('2026-11-18 16:49');
        Livewire::test(Queue::class)->set('stale', true)->assertDontSee($ticket->protocol);
        $this->setTime('2026-11-18 16:50');
        $this->get(route('ti.show', $ticket->id))->assertOk();
        Livewire::test(Queue::class)->set('stale', true)->assertSee($ticket->protocol)->assertSee('P4');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'priority' => 'P4']);
        $this->assertSame(2, DB::table('ticket_events')->where('ticket_id', $ticket->id)
            ->whereIn('event_type', ['ticket.opened', 'ticket.note'])->count());
    }

    public function test_dashboard_reconciles_visible_filtered_events_and_drilldowns(): void
    {
        $manager = $this->login(RoleName::Manager);
        $ticket = $this->open();
        $canceled = $this->open();
        $hidden = $this->open();
        DB::table('tickets')->where('id', $hidden->id)->update(['restricted' => true]);
        DB::table('tickets')->whereIn('id', [$ticket->id, $canceled->id, $hidden->id])->update([
            'opening_context' => json_encode(['units' => ['city' => 'Goiânia fictícia']]),
        ]);

        $this->setTime('2026-11-13 08:10');
        $this->record($ticket, $manager, 'classify', ['priority' => 'P1']);
        $this->record($ticket, $manager, 'reply', ['first_response_at' => now()]);
        $this->record($canceled, $manager, 'classify', ['priority' => 'P1']);
        $this->record($hidden, $manager, 'classify', ['priority' => 'P1']);
        $this->setTime('2026-11-13 09:00');
        $this->record($ticket, $manager, 'resolve', ['status' => 'resolvido']);
        $this->record($hidden, $manager, 'resolve', ['status' => 'resolvido']);
        $this->record($canceled, $manager, 'cancel', ['status' => 'cancelado'], ['reason' => 'Motivo fictício']);
        $this->setTime('2026-11-13 10:00');
        $this->record($ticket, $manager, 'reopen', ['status' => 'em_atendimento', 'cycle' => 2, 'first_response_at' => null]);
        $this->setTime('2026-11-13 14:00');
        $this->record($ticket, $manager, 'resolve', ['status' => 'resolvido']);

        $this->asUser($manager);
        $filters = ['inicio' => '2026-11-13', 'fim' => '2026-11-13', 'cidade' => 'Goiânia fictícia', 'priority' => 'P1'];
        $this->get(route('ti.dashboard', $filters + ['aba' => 'desempenho', 'indicador' => 'cycles']))
            ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['cycles'] === 2
                && $counts['resolved'] === 1 && $counts['reopened'] === 1
                && $counts['responses'] === 1 && $counts['responses_met'] === 1
                && $counts['solutions'] === 2 && $counts['solutions_met'] === 1)
            ->assertViewHas('details', fn ($list) => $list->total() === 2 && $list->pluck('id')->all() === [$ticket->id, $ticket->id])
            ->assertDontSee($hidden->protocol);
        $this->get(route('ti.dashboard', $filters + ['indicador' => 'canceled']))
            ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['entries'] === 2 && $counts['canceled'] === 1 && $counts['open'] === 0)
            ->assertSee($canceled->protocol)->assertSee('Motivo fictício');
        $this->get(route('ti.dashboard', $filters + ['aba' => 'recorrencias', 'indicador' => 'recurrence', 'grupo' => $ticket->category_id]))
            ->assertOk()->assertViewHas('details', fn ($list) => $list->total() === 1)
            ->assertSee($ticket->protocol)->assertDontSee($hidden->protocol);
        $this->get(route('ti.dashboard', $filters + ['indicador' => 'late_solution']))
            ->assertOk()->assertViewHas('details', fn ($list) => $list->total() === 0);
        DB::table('ticket_access_grants')->insert(['ticket_id' => $hidden->id, 'user_id' => $manager->id]);
        $this->get(route('ti.dashboard', $filters + ['aba' => 'desempenho', 'indicador' => 'resolved']))
            ->assertOk()->assertViewHas('details', fn ($list) => $list->total() === 2)->assertSee($hidden->protocol);
        $this->get(route('ti.dashboard', array_replace($filters, ['priority' => 'P4'])))
            ->assertOk()->assertViewHas('counts', fn ($counts) => array_sum($counts) === 0);
        $this->setTime('2026-11-20 08:59');
        $this->get(route('ti.dashboard', $filters + ['aba' => 'desempenho']))
            ->assertOk()->assertSee('Sem chamados com janela completa');
        $this->setTime('2026-11-20 09:00');
        $this->get(route('ti.dashboard', $filters + ['aba' => 'desempenho', 'indicador' => 'reopen_within']))
            ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['reopen_cohort'] === 2 && $counts['reopen_within'] === 1)
            ->assertViewHas('details', fn ($list) => $list->total() === 1 && $list->first()->id === $ticket->id)
            ->assertSee('50,0%');
        $this->get(route('ti.dashboard', $filters + ['aba' => 'desempenho', 'indicador' => 'solution_times']))
            ->assertOk()->assertViewHas('times', fn ($times) => (float) $times['solution_times']['values'][50] === 60.0
                && (float) $times['solution_times']['values'][90] === 204.0)
            ->assertViewHas('details', fn ($list) => $list->total() === 3);
        $this->login(RoleName::Support);
        $this->get(route('ti.dashboard', $filters + ['indicador' => 'entries']))->assertForbidden();
    }

    private function workAvailableJobs(): void
    {
        $ready = DB::table('jobs')->where('queue', 'ticket-alerts')->where('available_at', '<=', now()->timestamp)->count();
        for ($i = 0; $i < $ready; $i++) {
            $this->artisan('queue:work ticket-alerts --queue=ticket-alerts --once --sleep=0')->assertExitCode(0);
        }
    }

    private function setTime(string $date): CarbonImmutable
    {
        $instant = CarbonImmutable::parse($date, 'America/Sao_Paulo')->utc();
        $this->travelTo($instant);

        return $instant;
    }

    private function login(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role->value)->firstOrFail()->id]);
        $this->asUser($user);

        return $user;
    }

    private function asUser(User $user): void
    {
        $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function open(): object
    {
        $this->setTime('2026-11-13 08:00');
        $requester = $this->login(RoleName::Collaborator);
        $category = DB::table('categories')->insertGetId(['name' => 'Categoria fictícia '.Str::uuid()]);

        return app(RequesterTickets::class)->open($requester, [
            'subject' => 'Incidente fictício', 'description' => 'Descrição fictícia', 'category_id' => $category,
            'impact_scope' => 'equipe', 'work_condition' => 'impedido', 'started_unknown' => true,
            'affected_process' => 'Processo fictício',
        ], (string) Str::uuid());
    }

    private function record(object $ticket, User $actor, string $action, array $changes, array $details = []): void
    {
        TicketOperations::record(DB::table('tickets')->find($ticket->id), $actor->id, 'ticket.'.$action, $changes, $details, null);
    }
}
