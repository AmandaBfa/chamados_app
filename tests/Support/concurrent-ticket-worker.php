<?php

use App\Models\User;
use App\Services\RequesterTickets;
use App\Services\TicketOperations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Executado exclusivamente pelo ensaio de concorrência com fixtures fictícias.
putenv('APP_ENV=testing');
putenv('DB_URL=');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$_ENV['DB_URL'] = $_SERVER['DB_URL'] = '';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
if (! $app->environment('testing') || $identity->database !== 'chamados_test' || $identity->username !== 'chamados_test' || config('database.default') !== 'pgsql' || config('database.connections.pgsql.url')) {
    throw new RuntimeException('Ensaio permitido somente no PostgreSQL dedicado de testes.');
}
config(['session.driver' => 'array']);
$actor = User::findOrFail((int) $argv[1]);
session(['auth.version' => $actor->auth_version]);
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
if (($argv[4] ?? '') === 'close') {
    app(\App\Services\Sla\TicketSla::class)->process((int) $argv[2]);
    echo "\"processed\"\n";
    exit;
}
if (($argv[4] ?? '') === 'contest') {
    try {
        app(TicketOperations::class)->requesterAction($actor, (int) $argv[2], (int) $argv[3], 'request_reopen', 'Mesmo problema');
        echo "\"contested\"\n";
    } catch (ValidationException $exception) {
        if (! str_contains($exception->errors()['operation'][0] ?? '', 'Recarregue')) {
            throw $exception;
        }
        echo "\"conflict\"\n";
    }
    exit;
}
if (($argv[4] ?? '') === 'claim') {
    try {
        app(TicketOperations::class)->act($actor, (int) $argv[2], (int) $argv[3], 'claim', []);
        echo "\"claimed\"\n";
    } catch (ValidationException $exception) {
        if (! str_contains($exception->errors()['operation'][0] ?? '', 'Recarregue')) {
            throw $exception;
        }
        echo "\"conflict\"\n";
    }
    exit;
}
$ticket = app(RequesterTickets::class)->open($actor, [
    'subject' => 'Ensaio fictício concorrente', 'description' => 'Descrição fictícia do ensaio.',
    'category_id' => (int) $argv[2], 'impact_scope' => 'pessoa', 'affected_process' => 'Atividade fictícia',
    'work_condition' => 'continua', 'started_unknown' => true,
], $argv[3]);
echo json_encode(['id' => $ticket->id, 'protocol' => $ticket->protocol], JSON_THROW_ON_ERROR)."\n";
