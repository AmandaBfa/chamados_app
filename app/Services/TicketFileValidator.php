<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketFileValidator
{
    public function validate(array $files): array
    {
        Validator::make(['files' => $files], [
            'files' => ['array', 'max:'.config('tickets.max_files')],
            'files.*' => ['required', 'file', 'max:'.config('tickets.max_file_kb')],
        ], [], ['files' => 'anexos', 'files.*' => 'anexo'])->validate();

        return array_map(function ($file) {
            if (! $file instanceof UploadedFile) {
                $this->reject();
            }
            // Não confiar em MIME/tamanho declarados pelo navegador nem em UploadedFile::fake.
            $path = $file->getRealPath();
            $bytes = is_file($path) ? file_get_contents($path) : false;
            if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > config('tickets.max_file_kb') * 1024) {
                $this->reject();
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (! in_array($extension, config('tickets.mime_extensions')[$mime] ?? [], true)) {
                $this->reject();
            }
            if (str_starts_with($mime, 'image/')) {
                $image = @getimagesizefromstring($bytes);
                if (! $image || $image['mime'] !== $mime) {
                    $this->reject();
                }
            }
            if ($mime === 'text/plain' && (! mb_check_encoding($bytes, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $bytes))) {
                $this->reject();
            }
            if ($mime === 'application/pdf') {
                $decoded = preg_replace_callback('/#([0-9a-f]{2})/i', fn ($m) => chr(hexdec($m[1])), $bytes);
                if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF') ||
                    preg_match('~/\s*(JavaScript|JS|Launch|OpenAction|AA|EmbeddedFile|RichMedia|XFA|AcroForm|ObjStm|Encrypt)\b~i', $decoded)) {
                    $this->reject();
                }
            }
            $base = pathinfo(str_replace('\\', '/', $file->getClientOriginalName()), PATHINFO_FILENAME);
            $safeName = trim(preg_replace('/[^A-Za-z0-9_-]/', '-', Str::ascii($base)), '-');

            return ['file' => $file, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                'extension' => $extension, 'download_name' => substr($safeName ?: 'anexo', 0, 140).'.'.$extension];
        }, $files);
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['files' => 'Arquivo inválido. Envie PNG, JPEG, PDF simples sem recursos ativos ou TXT UTF-8, dentro do limite permitido.']);
    }
}
