<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccessAudit
{
    public static function record(string $action, ?User $actor, ?User $subject, array $details = []): void
    {
        // Somente metadados permitidos. Nunca aceitar senhas, tokens ou segredos MFA.
        $safe = array_intersect_key($details, array_flip(['reason', 'before_roles', 'after_roles', 'before_active', 'after_active', 'source']));
        DB::table('audit_events')->insert([
            'actor_id' => $actor?->id, 'subject_id' => $subject?->id, 'action' => $action,
            'details' => json_encode($safe, JSON_THROW_ON_ERROR), 'occurred_at' => now(),
        ]);
    }
}
