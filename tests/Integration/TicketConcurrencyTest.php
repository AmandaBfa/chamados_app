<?php

namespace Tests\Integration;

use App\Models\User;
use App\Services\RequesterTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TicketConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_open_one_ticket_with_one_event(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $actor = null;
        $category = null;
        $key = (string) Str::uuid();
        $processes = [];
        try {
            // Fixtures precisam estar COMMITADAS para serem visíveis nas duas conexões.
            // A limpeza final é direcionada somente a esses IDs; sem fresh/refresh/TRUNCATE.
            $actor = User::factory()->create();
            $category = DB::table('categories')->insertGetId(['name' => 'Concorrência fictícia '.$key]);
            $arguments = [(string) $actor->id, (string) $category, $key];
            $results = $this->runWorkers([$arguments, $arguments], $processes);
            $this->assertSame($results[0], $results[1]);
            $tickets = DB::table('tickets')->where('requester_id', $actor->id)->where('idempotency_key', $key);
            $this->assertSame(1, $tickets->count());
            $this->assertSame(1, DB::table('ticket_events')->where('ticket_id', $tickets->value('id'))->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if ($actor) {
                $ids = DB::table('tickets')->where('requester_id', $actor->id)->where('idempotency_key', $key)->pluck('id');
                DB::table('ticket_events')->whereIn('ticket_id', $ids)->delete();
                DB::table('tickets')->whereIn('id', $ids)->delete();
                DB::table('role_user')->where('user_id', $actor->id)->delete();
                DB::table('users')->where('id', $actor->id)->delete();
            }
            if ($category) {
                DB::table('categories')->where('id', $category)->delete();
            }
        }
    }

    public function test_two_technicians_cannot_claim_the_same_version(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $users = [];
        $processes = [];
        $team = $category = $ticket = null;
        $key = (string) Str::uuid();
        try {
            $role = DB::table('roles')->where('name', 'suporte')->value('id');
            $team = DB::table('teams')->insertGetId(['name' => 'Equipe fictícia '.$key]);
            $category = DB::table('categories')->insertGetId(['name' => 'Categoria fictícia '.$key]);
            foreach ([0, 1] as $index) {
                $users[$index] = User::factory()->create();
                $users[$index]->roles()->attach($role);
                DB::table('team_members')->insert(['team_id' => $team, 'user_id' => $users[$index]->id]);
            }
            session(['auth.version' => $users[0]->auth_version]);
            $ticket = app(RequesterTickets::class)->open($users[0], [
                'subject' => 'Conflito fictício', 'description' => 'Teste de atribuição concorrente.',
                'category_id' => $category, 'impact_scope' => 'pessoa', 'affected_process' => 'Atividade fictícia',
                'work_condition' => 'continua', 'started_unknown' => true,
            ], $key);
            DB::table('tickets')->where('id', $ticket->id)->update(['team_id' => $team, 'status' => 'em_triagem']);
            $arguments = array_map(fn (User $user) => [(string) $user->id, (string) $ticket->id, (string) $ticket->lock_version, 'claim'], $users);
            $results = $this->runWorkers($arguments, $processes);
            sort($results);
            $this->assertSame(['claimed', 'conflict'], $results);
            $this->assertContains(DB::table('tickets')->where('id', $ticket->id)->value('assignee_id'), array_map(fn (User $user) => $user->id, $users));
            $this->assertSame(1, DB::table('ticket_events')->where('ticket_id', $ticket->id)->where('event_type', 'ticket.claim')->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if ($ticket) {
                DB::table('ticket_events')->where('ticket_id', $ticket->id)->delete();
                DB::table('tickets')->where('id', $ticket->id)->delete();
            }
            foreach ($users as $user) {
                DB::table('team_members')->where('user_id', $user->id)->delete();
                DB::table('role_user')->where('user_id', $user->id)->delete();
                $user->delete();
            }
            if ($team) {
                DB::table('teams')->where('id', $team)->delete();
            }
            if ($category) {
                DB::table('categories')->where('id', $category)->delete();
            }
        }
    }

    private function runWorkers(array $arguments, array &$processes): array
    {
        $streams = [];
        foreach ($arguments as $n => $workerArguments) {
            $streams[$n] = new InputStream;
            $processes[$n] = new Process([PHP_BINARY, base_path('tests/Support/concurrent-ticket-worker.php'), ...$workerArguments], base_path(), null, $streams[$n], 30);
            $processes[$n]->start();
        }
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline && (! str_contains($processes[0]->getOutput(), 'READY') || ! str_contains($processes[1]->getOutput(), 'READY'))) {
            usleep(50000);
        }
        foreach ($processes as $process) {
            $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
        }
        foreach ($streams as $stream) {
            $stream->write("GO\n");
            $stream->close();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $lines = explode("\n", trim($process->getOutput()));
            $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
