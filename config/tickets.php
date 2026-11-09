<?php

return [
    // Proposta da especificação para desenvolvimento/piloto; limites por envio.
    'max_files' => 5,
    'max_file_kb' => 10240,
    'max_subject' => 160,
    'max_description' => 10000,
    'mime_extensions' => [
        'image/png' => ['png'], 'image/jpeg' => ['jpg', 'jpeg'],
        'application/pdf' => ['pdf'], 'text/plain' => ['txt'],
    ],
];
