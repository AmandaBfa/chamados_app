<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Ti\Detail;
use App\Livewire\Ti\Queue;
use App\Models\Role;
use App\Models\User;
use App\Services\RequesterTickets;
use App\Services\Sla\TicketSla;
use App\Services\TicketOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TicketOperationsTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginDedicatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $this->beginDedicatedTransaction();
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

    private function open(User $requester): object
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Atendimento fictício '.Str::uuid()]);

        return app(RequesterTickets::class)->open($requester, [
            'subject' => 'Incidente fictício', 'description' => 'Descrição para validação',
            'category_id' => $category, 'impact_scope' => 'equipe', 'affected_process' => 'Processo fictício',
            'work_condition' => 'impedido', 'started_unknown' => true,
        ], (string) Str::uuid());
    }

    public function test_sla_tracks_late_priority_pauses_transfer_reopening_and_automatic_close(): void
    {
        $at = fn (string $date) => CarbonImmutable::parse($date, 'America/Sao_Paulo')->utc();
        $this->travelTo($at('2026-11-13 16:00'));
        $requester = $this->login(RoleName::Collaborator);
        $ticket = $this->open($requester);
        $staff = $this->login(RoleName::Support);
        $team = DB::table('teams')->insertGetId(['name' => 'Equipe fictícia '.Str::uuid()]);
        DB::table('team_members')->insert(['team_id' => $team, 'user_id' => $staff->id]);
        $service = app(TicketOperations::class);
        $sla = app(TicketSla::class);
        $latest = fn () => DB::table('tickets')->find($ticket->id);
        $snapshot = fn () => $sla->snapshot($latest(), CarbonImmutable::now('UTC'));
        $act = fn ($action, $input = []) => $service->act($staff, $ticket->id, $latest()->lock_version, $action, $input);
        $classification = ['category_id' => $ticket->category_id, 'reason' => 'Impacto do teste'];
        $act('take_triage');
        $act('start');
        $this->travelTo($at('2026-11-13 16:30'));
        $act('wait_requester', ['body' => 'Qual tela apresentou erro?']);
        $this->travelTo($at('2026-11-16 08:45'));
        $act('classify', $classification + ['priority' => 'P2']);
        $this->assertTrue($snapshot()['breaches'][1]['response']->eq($at('2026-11-13 17:00')));
        $this->assertSame(1800, $snapshot()['clocks']['solution']['used']);
        $this->assertSame(4500, $snapshot()['paused_seconds']);
        $this->assertNull($snapshot()['clocks']['solution']['due']);
        $act('classify', $classification + ['priority' => 'P1']);
        $this->assertFalse($snapshot()['paused']);
        $this->assertTrue($snapshot()['clocks']['solution']['due']->eq($at('2026-11-16 12:15')));
        $this->asUser($requester);
        app(RequesterTickets::class)->comment($requester, $ticket->id, 'Na tela inicial', (string) Str::uuid());
        $this->asUser($staff);
        $act('wait_third', ['body' => 'Fornecedor de teste', 'protocol' => 'FOR-1']);
        $this->travelTo($at('2026-11-16 12:15'));
        $this->assertArrayNotHasKey('solution', $snapshot()['breaches'][1]);
        $this->travelTo($at('2026-11-16 12:15:01'));
        $this->assertTrue($snapshot()['breaches'][1]['solution']->eq($at('2026-11-16 12:15')));
        $due = $snapshot()['clocks']['solution']['due'];
        $act('assign', ['team_id' => $team, 'user_id' => $staff->id, 'reason' => 'Redistribuição']);
        $this->assertTrue($snapshot()['clocks']['solution']['due']->eq($due));
        $act('resume', ['reason' => 'Terceiro retornou']);
        $act('classify', $classification + ['priority' => 'P4']);
        $this->assertDatabaseHas('sla_breaches', ['ticket_id' => $ticket->id, 'cycle' => 1, 'clock' => 'solution']);
        $act('resolve', $classification + ['body' => 'Restabelecido']);
        $this->asUser($requester);
        $service->requesterAction($requester, $ticket->id, $latest()->lock_version, 'request_reopen', 'Mesmo erro');
        $this->asUser($staff);
        $act('reopen', ['reason' => 'Mesmo problema confirmado']);
        $this->assertSame(2, $snapshot()['cycle']);
        $this->assertArrayHasKey('solution', $snapshot()['breaches'][2]);
        $this->assertSame('sem resposta', $snapshot()['clocks']['response']['end']);
        $act('resolve', $classification + ['body' => 'Restabelecido novamente']);
        $this->asUser($requester);
        $service->requesterAction($requester, $ticket->id, $latest()->lock_version, 'request_reopen', 'Contesto a solução');
        $eligible = $snapshot()['close_at'];
        $this->travelTo($eligible);
        $sla->process($ticket->id, $eligible);
        $this->assertSame('resolvido', $latest()->status);
        $this->asUser($staff);
        $act('decline_reopen', ['reason' => 'O relato descreve outro problema']);
        $sla->process($ticket->id, $eligible);
        $sla->process($ticket->id, $eligible);
        $this->assertSame('fechado', $latest()->status);
        $this->assertSame(1, DB::table('ticket_events')->where('ticket_id', $ticket->id)->where('event_type', 'ticket.auto_closed')->count());
    }

    public function test_sla_configuration_requires_roles_and_preserves_previous_versions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 08:00', 'America/Sao_Paulo')->utc());
        $requester = $this->login(RoleName::Collaborator);
        $previous = $this->open($requester);
        $admin = $this->login(RoleName::Administrator);
        $admin->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['mfa.user_id' => $admin->id, 'auth.password_confirmed_at' => now()->timestamp]);
        $this->get(route('sla.index'))->assertOk();
        $this->post(route('sla.store'), [
            'base_version_id' => $previous->sla_version_id,
            'timezone' => 'America/Sao_Paulo',
            'weekly_windows' => json_encode(array_fill(1, 5, [['08:00', '17:00']])),
            'exceptions' => '{"2026-12-01":[]}',
            'targets' => ['P1' => [15, 240], 'P2' => [60, 540], 'P3' => [240, 1620], 'P4' => [540, 2700]],
            'reason' => 'Feriado fictício para teste',
        ])->assertRedirect(route('sla.index'));
        $version = DB::table('sla_versions')->where('configured_by', $admin->id)->firstOrFail();
        $this->assertNull($version->effective_at);
        $this->post(route('sla.approve', $version->id))->assertForbidden();
        $manager = $this->login(RoleName::Manager);
        $this->post(route('sla.store'))->assertForbidden();
        $this->post(route('sla.approve', $version->id))->assertRedirect(route('sla.index'));
        $this->assertDatabaseHas('sla_versions', ['id' => $version->id, 'approved_by' => $manager->id]);
        $this->asUser($requester);
        $this->get(route('sla.index'))->assertForbidden();
        $next = $this->open($requester);
        $this->assertSame($version->id, $next->sla_version_id);
        $this->assertDatabaseHas('tickets', ['id' => $previous->id, 'sla_version_id' => $previous->sla_version_id]);
        $now = CarbonImmutable::now('UTC')->addHour();
        $sla = app(TicketSla::class);
        $this->assertSame(3600, $sla->snapshot($previous, $now)['clocks']['response']['used']);
        $this->assertSame(0, $sla->snapshot($next, $now)['clocks']['response']['used']);
    }

    public function test_support_takes_triage_and_forwards_from_the_detail(): void
    {
        $requester = $this->login(RoleName::Collaborator);
        $ticket = $this->open($requester);
        $analyst = $this->login(RoleName::Analyst);
        $staff = $this->login(RoleName::Support);
        $supportTeam = DB::table('teams')->insertGetId(['name' => 'Suporte '.Str::uuid()]);
        $systemsTeam = DB::table('teams')->insertGetId(['name' => 'Sistemas '.Str::uuid()]);
        DB::table('team_members')->insert([
            ['team_id' => $supportTeam, 'user_id' => $staff->id],
            ['team_id' => $systemsTeam, 'user_id' => $analyst->id],
        ]);

        Livewire::test(Detail::class, ['ticketId' => $ticket->id])
            ->assertSee('Assumir e iniciar triagem')
            ->assertDontSee('Aguardar terceiro')
            ->call('chooseAction', 'take_triage')->assertHasNoErrors();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'em_triagem', 'team_id' => $supportTeam, 'assignee_id' => $staff->id]);

        Livewire::test(Detail::class, ['ticketId' => $ticket->id])
            ->assertSee('Responder ao colaborador')
            ->assertDontSee('Aguardar terceiro')
            ->call('chooseAction', 'forward')
            ->set('input.team_id', $systemsTeam)
            ->set('input.user_id', $analyst->id)
            ->set('input.reason', 'Análise pelo responsável pelo sistema')
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'em_atendimento', 'team_id' => $systemsTeam, 'assignee_id' => $analyst->id]);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'ticket.forward']);
        $this->asUser($analyst);
        Livewire::test(Detail::class, ['ticketId' => $ticket->id])->assertSee('Aguardar terceiro');
    }

    public function test_flow_records_public_and_internal_actions_waits_and_reopening(): void
    {
        $requester = $this->login(RoleName::Collaborator);
        $ticket = $this->open($requester);
        $staff = $this->login(RoleName::Support);
        $team = DB::table('teams')->insertGetId(['name' => 'Equipe fictícia '.Str::uuid()]);
        DB::table('team_members')->insert(['team_id' => $team, 'user_id' => $staff->id]);
        $service = app(TicketOperations::class);
        $latest = fn () => DB::table('tickets')->find($ticket->id);
        $act = fn (string $action, array $input = []) => $service->act($staff, $ticket->id, $latest()->lock_version, $action, $input);
        $this->get(route('ti.index'))->assertOk()->assertSee($ticket->protocol);
        Livewire::test(Detail::class, ['ticketId' => $ticket->id])->set('action', 'triage')->call('save')->assertHasNoErrors();
        $act('classify', ['category_id' => $ticket->category_id, 'priority' => 'P2', 'reason' => 'Processo fictício bloqueado']);
        $act('assign', ['team_id' => $team, 'reason' => 'Fila de suporte']);
        $stale = $latest()->lock_version;
        $act('claim');
        try {
            $service->act($staff, $ticket->id, $stale, 'assign', ['team_id' => $team, 'reason' => 'Tela desatualizada']);
            $this->fail('Uma versão antiga sobrescreveu a atribuição.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation', $exception->errors());
        }
        $this->assertSame($staff->id, $latest()->assignee_id);
        Livewire::test(Queue::class)->set('filters.status', 'em_atendimento')->set('filters.team_id', (string) $team)
            ->set('search', $ticket->protocol)->assertSee($ticket->subject);
        $act('note', ['body' => 'Diagnóstico exclusivamente interno', 'useful' => true]);
        $this->assertNull($latest()->first_response_at);
        $act('wait_requester', ['body' => 'Em qual tela ocorre o erro?', 'useful' => true]);
        $this->assertNotNull($latest()->first_response_at);

        $this->asUser($requester);
        $this->get(route('portal.show', $ticket->id))->assertOk()->assertSee('Em qual tela ocorre o erro?')->assertDontSee('Diagnóstico exclusivamente interno');
        app(RequesterTickets::class)->comment($requester, $ticket->id, 'Na tela de teste', (string) Str::uuid());
        $this->assertSame('em_atendimento', $latest()->status);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'ticket.requester_returned']);

        $this->asUser($staff);
        $this->get(route('ti.show', $ticket->id))->assertOk()
            ->assertSee($requester->name)->assertSee($requester->email)->assertSee('Na tela de teste');
        $act('wait_third', ['body' => 'Fornecedor fictício', 'protocol' => 'FOR-1']);
        $act('resume', ['reason' => 'Fornecedor respondeu']);
        Livewire::test(Detail::class, ['ticketId' => $ticket->id])->set('action', 'resolve')->call('save')->assertHasErrors('operation');
        $act('resolve', ['category_id' => $ticket->category_id, 'body' => 'Configuração corrigida.', 'restoration' => 'Acesso temporário']);
        $this->assertSame('resolvido', $latest()->status);
        $this->asUser($requester);
        $service->requesterAction($requester, $ticket->id, $latest()->lock_version, 'confirm', '');
        $this->assertSame('fechado', $latest()->status);
        $service->requesterAction($requester, $ticket->id, $latest()->lock_version, 'request_reopen', 'O mesmo erro reapareceu.');
        $this->asUser($staff);
        $act('reopen', ['reason' => 'Reproduzido o mesmo problema']);
        $this->assertSame(2, $latest()->cycle);
        $this->assertNull($latest()->first_response_at);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'ticket.resolve']);
        Livewire::test(Detail::class, ['ticketId' => $ticket->id])->set('action', 'cancel')->call('save')->assertHasErrors('operation');
        $act('cancel', ['reason' => 'Duplicidade confirmada']);
        $this->assertSame('cancelado', $latest()->status);
    }

    public function test_operational_scope_covers_profiles_and_requires_explicit_grant(): void
    {
        $requester = $this->login(RoleName::Collaborator);
        $ticket = $this->open($requester);
        $this->get(route('ti.index'))->assertForbidden();
        foreach ([RoleName::Apprentice, RoleName::Developer] as $role) {
            $this->login($role);
            $this->get(route('ti.show', $ticket->id))->assertNotFound();
        }
        foreach ([RoleName::Support, RoleName::Analyst, RoleName::Manager] as $role) {
            $this->login($role);
            $this->get(route('ti.show', $ticket->id))->assertOk();
        }
        $admin = $this->login(RoleName::Administrator);
        $admin->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->withSession(['mfa.user_id' => $admin->id]);
        $this->get(route('ti.index'))->assertForbidden();

        DB::table('tickets')->where('id', $ticket->id)->update(['restricted' => true]);
        $staff = $this->login(RoleName::Manager);
        Livewire::test(Queue::class)->set('search', $ticket->protocol)->assertDontSee($ticket->subject);
        $this->get(route('ti.show', $ticket->id))->assertNotFound();
        $this->get(route('ti.attachment', [$ticket->id, 1]))->assertNotFound();
        DB::table('ticket_access_grants')->insert(['ticket_id' => $ticket->id, 'user_id' => $staff->id]);
        $this->get(route('ti.show', $ticket->id))->assertOk()->assertSee($ticket->subject);
        $component = Livewire::test(Detail::class, ['ticketId' => $ticket->id])->set('action', 'note')->set('input.body', 'Nota de teste');
        DB::table('ticket_access_grants')->where('ticket_id', $ticket->id)->where('user_id', $staff->id)->delete();
        $component->call('save')->assertStatus(404);
    }
}
