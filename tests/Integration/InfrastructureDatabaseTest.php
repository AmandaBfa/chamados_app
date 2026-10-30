<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureDatabaseTest extends TestCase
{
    public function test_dedicated_postgresql_infrastructure_persists_data(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('chamados_test', config('database.connections.pgsql.database'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $this->assertSame('UTC', DB::selectOne("SELECT current_setting('TimeZone') AS timezone")->timezone);

        foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabela ausente: {$table}");
        }

        DB::beginTransaction();
        try {
            $key = 'foundation-check-'.bin2hex(random_bytes(16));
            DB::table('cache')->insert(['key' => $key, 'value' => 'verification', 'expiration' => time() + 60]);
            $this->assertSame('verification', DB::table('cache')->where('key', $key)->value('value'));
        } finally {
            DB::rollBack();
        }
    }
}
