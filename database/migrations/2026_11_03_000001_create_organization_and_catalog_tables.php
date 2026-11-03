<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['units', 'departments', 'teams', 'categories', 'subcategories', 'services', 'equipment_references'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('seed_key')->nullable()->unique();
                $table->string('name', 160);
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('version')->default(1);
                if ($name === 'units') {
                    $table->string('city', 160);
                }
                if ($name === 'teams') {
                    $table->boolean('specific_assignments_only')->default(false);
                    $table->boolean('triage_coverage')->default(false);
                    $table->boolean('critical_alternative')->default(false);
                    foreach (['reference_user_id', 'substitute_user_id'] as $field) {
                        $table->foreignId($field)->nullable()->constrained('users')->restrictOnDelete();
                    }
                }
                if (in_array($name, ['subcategories', 'services'])) {
                    $table->foreignId('category_id')->nullable($name === 'services')->constrained('categories')->restrictOnDelete();
                }
                if ($name === 'services') {
                    $table->foreignId('subcategory_id')->nullable();
                    $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete();
                    $table->foreignId('reference_user_id')->nullable()->constrained('users')->restrictOnDelete();
                    $table->string('proposed_criticality', 30)->nullable();
                    $table->string('supplier', 160)->nullable();
                }
                if ($name === 'equipment_references') {
                    $table->string('identifier', 160)->nullable();
                    $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
                }
                $table->timestampsTz();
                if ($name === 'subcategories') {
                    $table->unique(['category_id', 'name']);
                    $table->unique(['category_id', 'id']);
                } else {
                    $table->unique('name');
                }
            });
        }
        Schema::table('services', function (Blueprint $table) {
            $table->foreign(['category_id', 'subcategory_id'])->references(['category_id', 'id'])->on('subcategories')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_subcategory_parent CHECK (subcategory_id IS NULL OR category_id IS NOT NULL)');
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_criticality CHECK (proposed_criticality IS NULL OR proposed_criticality IN ('baixa','normal','alta','critica'))");
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->unsignedInteger('organization_version')->default(1);
        });
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->unique(['team_id', 'user_id']);
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('organization_version');
        });
        foreach (['equipment_references', 'services', 'subcategories', 'categories', 'teams', 'departments', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
