<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_versions', function (Blueprint $table) {
            $table->id();
            $table->string('timezone', 64);
            $table->jsonb('weekly_windows');
            $table->jsonb('exceptions');
            $table->jsonb('targets');
            $table->text('reason');
            $table->foreignId('base_version_id')->nullable()->constrained('sla_versions')->restrictOnDelete();
            $table->foreignId('configured_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('effective_at')->nullable()->index();
        });
        DB::statement('ALTER TABLE sla_versions ADD CONSTRAINT sla_versions_approval_check CHECK (
            (configured_by IS NULL AND approved_by IS NULL AND effective_at IS NOT NULL AND base_version_id IS NULL)
            OR (configured_by IS NOT NULL AND base_version_id IS NOT NULL AND ((approved_by IS NULL) = (effective_at IS NULL)))
        )');
        $initialVersion = DB::table('sla_versions')->insertGetId([
            'timezone' => 'America/Sao_Paulo',
            'weekly_windows' => json_encode(array_fill(1, 5, [['08:00', '17:00']]), JSON_THROW_ON_ERROR),
            'exceptions' => '{}',
            'targets' => json_encode(['P1' => [15, 240], 'P2' => [60, 540], 'P3' => [240, 1620], 'P4' => [540, 2700]], JSON_THROW_ON_ERROR),
            'reason' => 'Configuração inicial de desenvolvimento/piloto.',
            'created_at' => now(),
            'effective_at' => now(),
        ]);
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('sla_version_id')->nullable()->constrained('sla_versions')->restrictOnDelete();
            $table->index(['sla_version_id', 'status']);
        });
        DB::table('tickets')->whereNull('sla_version_id')->update(['sla_version_id' => $initialVersion]);
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->change();
        });
        Schema::create('sla_breaches', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cycle');
            $table->string('clock', 16);
            $table->timestampTz('breached_at');
            $table->timestampTz('detected_at');
            $table->primary(['ticket_id', 'cycle', 'clock']);
        });
        DB::statement("ALTER TABLE sla_breaches ADD CONSTRAINT sla_breaches_clock_check CHECK (cycle > 0 AND clock IN ('response', 'solution'))");
        DB::statement("ALTER TABLE ticket_events ADD CONSTRAINT ticket_events_sla_actor_check CHECK (actor_id IS NOT NULL OR event_type = 'ticket.auto_closed')");
        Schema::create('sla_deadline_revisions', function (Blueprint $table) {
            $table->foreignId('ticket_event_id')->constrained('ticket_events')->restrictOnDelete();
            $table->string('clock', 16);
            $table->timestampTz('previous_due_at')->nullable();
            $table->timestampTz('new_due_at')->nullable();
            $table->primary(['ticket_event_id', 'clock']);
        });
        DB::statement("ALTER TABLE sla_deadline_revisions ADD CONSTRAINT sla_revisions_clock_check CHECK (clock IN ('response', 'solution'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_deadline_revisions');
        Schema::dropIfExists('sla_breaches');
        DB::statement('ALTER TABLE ticket_events DROP CONSTRAINT ticket_events_sla_actor_check');
        // Preserve history. PostgreSQL rejects rollback of this constraint if automatic events already exist.
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable(false)->change();
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['sla_version_id', 'status']);
            $table->dropConstrainedForeignId('sla_version_id');
        });
        Schema::dropIfExists('sla_versions');
    }
};
