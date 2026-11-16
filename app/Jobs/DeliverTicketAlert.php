<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Alerts\AlertRecipients;
use App\Services\Sla\TicketSla;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class DeliverTicketAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public array $backoff = [60, 180];

    public function __construct(public readonly int $alertId) {}

    public function handle(AlertRecipients $recipients, TicketSla $sla): void
    {
        $failed = DB::transaction(function () use ($recipients, $sla) {
            $alert = DB::table('ticket_alerts')->where('id', $this->alertId)->lockForUpdate()->first();
            if (! $alert || $alert->status !== 'queued') {
                return false;
            }

            $user = User::find($alert->recipient_id);
            $status = 'skipped';
            if ($user && $recipients->eligible($user, $alert->ticket_id)) {
                $version = DB::table('tickets')->where('id', $alert->ticket_id)->value('sla_version_id');
                $calendar = $version ? $sla->calendar($version) : null;
                $now = CarbonImmutable::now('UTC')->setMicrosecond(0);
                if ($calendar && ! $calendar->covers($now)) {
                    Bus::dispatch((new self($alert->id))->onConnection('ticket-alerts')->beforeCommit()
                        ->delay($calendar->addSeconds($now, 1)));

                    return false;
                }
                try {
                    if ($alert->channel === 'mail') {
                        $this->sendMail($user, $alert->ticket_id);
                    }
                    $status = 'sent';
                } catch (Throwable) {
                    // O erro do transporte pode conter o endereço. O log da fila recebe somente o ID.
                    $status = $alert->attempts + 1 >= $this->tries ? 'failed' : 'queued';
                }
            }
            DB::table('ticket_alerts')->where('id', $alert->id)->update([
                'status' => $status, 'attempts' => $alert->attempts + 1, 'updated_at' => now(),
            ]);

            return in_array($status, ['queued', 'failed'], true);
        });

        if ($failed) {
            throw new RuntimeException('Falha na entrega do alerta '.$this->alertId);
        }
    }

    private function sendMail(User $user, int $ticket): void
    {
        if (! config('ticket_alerts.local_mail') || ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Captura local indisponível.');
        }
        $protocol = DB::table('tickets')->where('id', $ticket)->value('protocol');
        $url = route('ti.show', $ticket);
        Mail::mailer(app()->environment('testing') ? 'array' : 'ticket_alert_capture')
            ->raw("Protocolo {$protocol}\n{$url}", fn ($message) => $message
                ->to($user->email)->subject('Aviso do sistema de chamados'));
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('ticket_alerts')->where('id', $this->alertId)->where('status', 'queued')
            ->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
