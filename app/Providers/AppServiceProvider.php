<?php

namespace App\Providers;

use App\Http\Controllers\DisabledUploadPreviewController;
use App\Http\Controllers\PortalTemporaryUploadController;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Features\SupportFileUploads\FileUploadController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FileUploadController::class, PortalTemporaryUploadController::class);
        $this->app->bind(FilePreviewController::class, DisabledUploadPreviewController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.config('tickets.max_file_kb')],
            'livewire.temporary_file_upload.middleware' => ['auth', 'verified', 'admin.mfa', 'throttle:30,1'],
            'livewire.temporary_file_upload.preview_mimes' => [],
        ]);
    }
}
