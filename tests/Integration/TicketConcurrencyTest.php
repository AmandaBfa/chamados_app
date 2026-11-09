<?php

namespace Tests\Integration;

use App\Models\User;
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
            $streams = [];
            for ($n = 0; $n < 2; $n++) {
                $streams[$n] = new InputStream;
                $processes[$n] = new Process([PHP_BINARY, base_path('tests/Support/concurrent-ticket-worker.php'), (string) $actor->id, (string) $category, $key], base_path(), null, $streams[$n], 30);
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
}
