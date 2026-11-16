<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_versions', function (Blueprint $table) {
            $table->jsonb('exception_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sla_versions', function (Blueprint $table) {
            $table->dropColumn('exception_notes');
        });
    }
};
