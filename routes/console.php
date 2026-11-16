<?php

use App\Services\Alerts\TicketAlerts;
use App\Services\Sla\TicketSla;
use App\Services\TicketActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sla:processar', function (TicketSla $sla) {
    DB::table('tickets')->whereNotNull('sla_version_id')->select('id')->chunkById(100, function ($tickets) use ($sla) {
        foreach ($tickets as $ticket) {
            $sla->process($ticket->id);
        }
    });
})->purpose('Registrar violações de SLA e fechar chamados elegíveis');

Schedule::command('sla:processar')->everyMinute()->withoutOverlapping();

Artisan::command('alertas:processar', function (TicketAlerts $alerts) {
    $now = CarbonImmutable::now('UTC')->setMicrosecond(0);
    DB::table('tickets')->whereIn('status', TicketActivity::ACTIVE)->select('id')
        ->chunkById(100, function ($tickets) use ($alerts, $now) {
            foreach ($tickets as $ticket) {
                $alerts->process($ticket->id, $now);
            }
        });
})->purpose('Detectar marcos operacionais e enfileirar alertas');

Schedule::command('alertas:processar')->everyMinute()->withoutOverlapping();
