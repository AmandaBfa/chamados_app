<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 40)->unique();
            $table->uuid('idempotency_key');
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->jsonb('opening_context');
            $table->string('type', 20)->default('incidente');
            $table->string('subject', 160);
            $table->text('description');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable();
            $table->foreign(['category_id', 'subcategory_id'])->references(['category_id', 'id'])->on('subcategories')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('equipment_reference_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('impact_scope', 20);
            $table->string('affected_process', 500);
            $table->string('work_condition', 25);
            $table->boolean('started_unknown');
            $table->timestampTz('started_at')->nullable();
            $table->string('status', 30)->default('novo');
            $table->string('priority', 2)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->unique(['requester_id', 'idempotency_key']);
            $table->index(['requester_id', 'created_at', 'id']);
            $table->index(['requester_id', 'status']);
        });
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_type CHECK (type IN ('incidente','solicitacao'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status CHECK (status IN ('novo','em_triagem','em_atendimento','aguardando_solicitante','aguardando_terceiro','resolvido','fechado','cancelado'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_priority CHECK (priority IS NULL OR priority IN ('P1','P2','P3','P4'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_impact CHECK (impact_scope IN ('pessoa','equipe','unidade','maior'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_work CHECK (work_condition IN ('continua','parcialmente_impedido','impedido'))");
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_start CHECK ((started_unknown AND started_at IS NULL) OR (NOT started_unknown AND started_at IS NOT NULL))');
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->text('body');
            $table->string('visibility', 10);
            $table->timestampsTz();
            $table->unique(['ticket_id', 'author_id', 'idempotency_key']);
            $table->unique(['ticket_id', 'id']);
            $table->index(['ticket_id', 'visibility', 'id']);
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('comment_id')->nullable();
            $table->foreign(['ticket_id', 'comment_id'])->references(['ticket_id', 'id'])->on('comments')->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('storage_key')->unique();
            $table->string('download_name', 180);
            $table->string('mime', 80);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('visibility', 10);
            $table->timestampsTz();
            $table->index(['ticket_id', 'visibility', 'comment_id']);
        });
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('visibility', 10);
            $table->string('public_summary', 500)->nullable();
            $table->jsonb('details');
            $table->timestampTz('occurred_at');
            $table->index(['ticket_id', 'visibility', 'id']);
        });
        foreach (['comments', 'attachments', 'ticket_events'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_visibility CHECK (visibility IN ('public','internal'))");
        }
        Schema::create('temporary_upload_receipts', function (Blueprint $table) {
            $table->char('path_hash', 64)->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['temporary_upload_receipts', 'ticket_events', 'attachments', 'comments', 'tickets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
