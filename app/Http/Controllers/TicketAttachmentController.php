<?php

namespace App\Http\Controllers;

use App\Services\RequesterTickets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function __invoke(Request $request, int $ticket, int $attachment, RequesterTickets $service)
    {
        $file = $service->attachment($request->user(), $ticket, $attachment);
        abort_unless(Storage::disk('local')->exists($file->storage_key), 404);

        return Storage::disk('local')->download($file->storage_key, $file->download_name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store',
        ]);
    }
}
