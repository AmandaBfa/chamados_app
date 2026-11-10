<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Ti\Detail;
use App\Livewire\Ti\Queue;
use App\Models\Role;
use App\Models\User;
use App\Services\RequesterTickets;
use App\Services\TicketOperations;
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
