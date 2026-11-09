<?php

namespace App\Http\Controllers;

use Livewire\Features\SupportFileUploads\FilePreviewController;

class DisabledUploadPreviewController extends FilePreviewController
{
    public function handle($filename)
    {
        abort(404);
    }
}
