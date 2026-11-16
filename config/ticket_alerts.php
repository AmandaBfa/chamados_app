<?php

return [
    // Parâmetros provisórios de piloto, em minutos de cobertura.
    'unassigned_minutes' => ['P1' => 5, 'P2' => 30, 'P3' => 120, 'P4' => 120, 'unclassified' => 120],
    'stale_minutes' => 1080,
    'critical_suspicion_scopes' => ['equipe', 'unidade', 'maior'],
    'response_warning_percent' => 70,
    'resume_lookback_minutes' => 1440,
    'local_mail' => env('TICKET_ALERT_LOCAL_MAIL', false),
];
