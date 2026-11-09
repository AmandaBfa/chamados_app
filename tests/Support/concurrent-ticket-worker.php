<?php

use App\Models\User;
use App\Services\RequesterTickets;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
$ticket = app(RequesterTickets::class)->open($actor, [
    'subject' => 'Ensaio fictício concorrente', 'description' => 'Descrição fictícia do ensaio.',
    'category_id' => (int) $argv[2], 'impact_scope' => 'pessoa', 'affected_process' => 'Atividade fictícia',
    'work_condition' => 'continua', 'started_unknown' => true,
], $argv[3]);
echo json_encode(['id' => $ticket->id, 'protocol' => $ticket->protocol], JSON_THROW_ON_ERROR)."\n";
