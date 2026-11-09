<?php

namespace App\Livewire\Portal;

use App\Services\PortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait PortalUploads
{
    use WithFileUploads {
        _startUpload as protected startTemporaryUpload;
        _finishUpload as protected finishTemporaryUpload;
        _removeUpload as protected removeTemporaryUpload;
        _uploadErrored as protected reportUploadError;
    }

    private function authorizeUpload(string $name): void
    {
        abort_unless($name === 'files', 422);
        app(PortalAccess::class)->authorize(auth()->user());
        if (isset($this->ticketId)) {
            app(PortalAccess::class)->own(auth()->user(), $this->ticketId);
        }
    }

    private function ownedTemporaryPath(string $path): void
    {
        abort_unless(DB::table('temporary_upload_receipts')->where('path_hash', hash('sha256', basename(str_replace('\\', '/', $path))))
            ->where('user_id', auth()->id())->whereNull('consumed_at')->where('expires_at', '>', now())->exists(), 403);
    }

    public function _startUpload($name, $fileInfo, $isMultiple)
    {
        $this->authorizeUpload($name);

        return $this->startTemporaryUpload($name, $fileInfo, $isMultiple);
    }

    public function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
    {
        $this->authorizeUpload($name);
        foreach ($tmpPath as $signedPath) {
            $path = TemporaryUploadedFile::extractPathFromSignedPath($signedPath);
            abort_if($path === false, 403);
            $this->ownedTemporaryPath($path);
        }

        return $this->finishTemporaryUpload($name, $tmpPath, $isMultiple, $append);
    }

    public function _removeUpload($name, $tmpFilename)
    {
        $this->authorizeUpload($name);
        $this->ownedTemporaryPath($tmpFilename);

        return $this->removeTemporaryUpload($name, $tmpFilename);
    }

    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        $this->authorizeUpload($name);

        return $this->reportUploadError($name, $errorsInJson, $isMultiple);
    }

    public function removeFile(int $index): void
    {
        $this->authorizeUpload('files');
        abort_unless(isset($this->files[$index]) && $this->files[$index] instanceof TemporaryUploadedFile, 422);
        $this->_removeUpload('files', $this->files[$index]->getFilename());
    }

    public function updatedFiles(): void
    {
        $this->authorizeUpload('files');
        Validator::make(['files' => $this->files], ['files' => ['array', 'max:'.config('tickets.max_files')]], [], ['files' => 'anexos'])->validate();
        foreach ($this->files as $file) {
            abort_unless($file instanceof TemporaryUploadedFile, 422);
            $this->ownedTemporaryPath($file->getFilename());
        }
    }
}
