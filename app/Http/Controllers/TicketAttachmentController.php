<?php

namespace App\Http\Controllers;

use App\Services\RequesterTickets;
use App\Services\TicketOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function __invoke(Request $request, int $ticket, int $attachment, RequesterTickets $service)
    {
        return $this->download($service->attachment($request->user(), $ticket, $attachment));
    }

    public function staff(Request $request, int $ticket, int $attachment, TicketOperations $service)
    {
        $service->ticket($request->user(), $ticket);
        $file = DB::table('attachments')->where('ticket_id', $ticket)->where('id', $attachment)->first();
        abort_unless($file, 404);

        return $this->download($file);
    }

    private function download(object $file)
    {
        abort_unless(Storage::disk('local')->exists($file->storage_key), 404);

        return Storage::disk('local')->download($file->storage_key, $file->download_name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store',
        ]);
    }
}
