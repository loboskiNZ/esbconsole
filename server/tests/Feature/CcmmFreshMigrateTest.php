<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CcmmFreshMigrateTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const FORBIDDEN_TABLES = [
        'invite_links',
        'invite_link_acceptances',
        'runtime_events',
        'console_learning_snapshots',
        'integration_devices',
    ];

    public function test_fresh_migrate_creates_ccmm_tables_without_forbidden_tables(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('CCMM validation requires PostgreSQL.');
        }

        $this->assertTrue(Schema::hasTable('bands'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('show_console_baselines'));
        $this->assertTrue(Schema::hasTable('mix_moves'));
        $this->assertTrue(Schema::hasColumn('users', 'public_id'));
        $this->assertTrue(Schema::hasColumn('show_console_baselines', 'baseline_json'));

        foreach (self::FORBIDDEN_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Forbidden table present: {$table}");
        }

        $this->assertSame(0, Artisan::call('ccmm:validate-schema'));
    }
}
