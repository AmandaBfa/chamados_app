<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cycle');
            $table->string('clock', 12);
            $table->string('milestone', 32);
            $table->string('audience', 16);
            $table->foreignId('recipient_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('channel', 12);
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('detected_at');
            $table->timestampTz('updated_at');
            $table->index(['recipient_id', 'channel', 'status', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX ticket_alert_delivery ON ticket_alerts (ticket_id, cycle, clock, milestone, recipient_id, channel) WHERE recipient_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ticket_alert_missing ON ticket_alerts (ticket_id, cycle, clock, milestone, audience) WHERE recipient_id IS NULL');
        DB::statement("ALTER TABLE ticket_alerts ADD CHECK (cycle > 0 AND clock IN ('operation', 'response', 'solution') AND ((recipient_id IS NULL AND channel = 'audit' AND status = 'missing') OR (recipient_id IS NOT NULL AND channel IN ('inbox', 'mail') AND status IN ('queued', 'sent', 'failed', 'skipped'))))");
        DB::statement('CREATE INDEX ticket_events_activity ON ticket_events (ticket_id, occurred_at DESC, id DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_alerts');
        DB::statement('DROP INDEX ticket_events_activity');
    }
};
