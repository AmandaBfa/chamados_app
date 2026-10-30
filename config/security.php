<?php

return [
    // Parâmetros técnicos iniciais para desenvolvimento/piloto.
    'invitation_hours' => (int) env('INVITATION_HOURS', 72),
    'bootstrap_enabled' => (bool) env('AUTH_BOOTSTRAP_ENABLED', false),
];
