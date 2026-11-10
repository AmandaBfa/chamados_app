<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('restricted')->default(false);
            $table->unsignedInteger('cycle')->default(1);
            $table->timestampTz('reopen_requested_at')->nullable();
            $table->timestampTz('first_response_at')->nullable();
            $table->foreignId('related_ticket_id')->nullable()->constrained('tickets')->restrictOnDelete();
            $table->index(['team_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });
        Schema::create('ticket_participants', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['ticket_id', 'user_id']);
        });
        Schema::create('ticket_access_grants', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_access_grants');
        Schema::dropIfExists('ticket_participants');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'status']);
            $table->dropIndex(['assignee_id', 'status']);
            foreach (['team_id', 'assignee_id', 'related_ticket_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['restricted', 'cycle', 'reopen_requested_at', 'first_response_at']);
        });
    }
};
