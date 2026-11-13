<?php

use App\Services\Sla\TicketSla;
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
