<?php

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 254)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('local_login_enabled')->default(true);
            $table->unsignedInteger('auth_version')->default(0);
            $table->string('job_title')->nullable();
            $table->string('support_level', 20)->nullable();
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_normalized CHECK (email = lower(trim(email)))');
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('label');
        });
        foreach (RoleName::cases() as $role) {
            DB::table('roles')->insert(['name' => $role->value, 'label' => $role->label()]);
        }
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('login_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('organization_id');
            $table->string('subject');
            $table->timestampsTz();
            $table->unique(['provider', 'organization_id', 'subject']);
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80)->index();
            $table->jsonb('details');
            $table->timestampTz('occurred_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'sessions', 'password_reset_tokens', 'invitations', 'login_identities', 'role_user', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
