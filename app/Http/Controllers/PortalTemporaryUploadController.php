<?php

namespace App\Http\Controllers;

use App\Services\PortalAccess;
use App\Services\TicketFileValidator;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PortalTemporaryUploadController extends FileUploadController
{
    public function validateAndStore($files, $disk)
    {
        app(PortalAccess::class)->authorize(auth()->user());
        app(TicketFileValidator::class)->validate($files ?? []);
        $paths = parent::validateAndStore($files, $disk);
        foreach ($paths as $signedPath) {
            $path = TemporaryUploadedFile::extractPathFromSignedPath($signedPath);
            DB::table('temporary_upload_receipts')->insert([
                'path_hash' => hash('sha256', basename(str_replace('\\', '/', $path))), 'user_id' => auth()->id(), 'expires_at' => now()->addDay(),
            ]);
        }

        return $paths;
    }
}
