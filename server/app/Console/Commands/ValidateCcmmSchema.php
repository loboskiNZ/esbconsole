<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidateCcmmSchema extends Command
{
    protected $signature = 'ccmm:validate-schema {--json : Output JSON report}';

    protected $description = 'Validate CCMM shared schema against PH059 expectations (local only)';

    /** @var list<string> */
    private const CCMM_TABLES = [
        'bands',
        'users',
        'people',
        'person_secure_fields',
        'person_files',
        'person_iem_settings',
        'person_instruments',
        'musicians',
        'musician_band_roles',
        'instrument_reference',
        'song_moods',
        'time_signatures',
        'musical_keys',
        'songs',
        'cues',
        'instrument_parts',
        'import_batches',
        'import_entity_mappings',
        'charts',
        'song_instrument_parts',
        'snippets',
        'action_types',
        'action_definitions',
        'action_parameters',
        'cue_actions',
        'ableton_show_files',
        'shows',
        'show_playlist_items',
        'performances',
        'performance_assignments',
        'devices',
        'capabilities',
        'assignments',
        'venues',
        'festivals',
        'effect_package_types',
        'effects',
        'effect_parameters',
        'effect_definitions',
        'effect_packages',
        'effect_package_items',
        'effect_package_item_parameters',
        'effect_package_item_target_sections',
        'song_effect_assignments',
        'show_console_baselines',
        'mix_moves',
        'person_invitations',
        'cloud_recovery_entity_map',
    ];

    /** @var list<string> */
    private const LARAVEL_INFRA = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'migrations',
    ];

    /** @var list<string> */
    private const FORBIDDEN = [
        'invite_links',
        'invite_link_acceptances',
        'runtime_events',
        'runtime_action_plans',
        'runtime_action_items',
        'runtime_audit_records',
        'runtime_dispatches',
        'runtime_dispatch_items',
        'console_learning_snapshots',
        'integration_devices',
        'integration_connection_profiles',
        'performance_device_assignments',
        'effect_library_items',
        'effect_library_parameters',
    ];

    public function handle(): int
    {
        $existing = collect(DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        ))->pluck('tablename')->values();

        $missing = collect(self::CCMM_TABLES)->reject(fn (string $t) => Schema::hasTable($t))->values();
        $unexpected = $existing->diff(collect(self::CCMM_TABLES)->merge(self::LARAVEL_INFRA))->values();
        $forbiddenPresent = collect(self::FORBIDDEN)->filter(fn (string $t) => Schema::hasTable($t))->values();

        $fkViolations = $this->findOrphanForeignKeys();
        $indexChecks = $this->spotCheckIndexes();

        $report = [
            'database' => config('database.connections.'.config('database.default').'.database'),
            'connection' => config('database.default'),
            'table_count' => $existing->count(),
            'ccmm_expected' => count(self::CCMM_TABLES),
            'ccmm_present' => count(self::CCMM_TABLES) - $missing->count(),
            'missing_tables' => $missing->all(),
            'unexpected_tables' => $unexpected->all(),
            'forbidden_tables_present' => $forbiddenPresent->all(),
            'fk_orphan_violations' => $fkViolations,
            'index_checks' => $indexChecks,
            'package_order' => $this->migrationOrder(),
            'passed' => $missing->isEmpty() && $forbiddenPresent->isEmpty() && $fkViolations === [],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->renderTextReport($report);
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{table: string, column: string, orphans: int}>
     */
    private function findOrphanForeignKeys(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $violations = [];
        $rows = DB::select(<<<'SQL'
            SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
        SQL);

        foreach ($rows as $row) {
            $orphans = DB::table($row->table_name)
                ->whereNotNull($row->column_name)
                ->whereNotExists(function ($query) use ($row) {
                    $query->select(DB::raw('1'))
                        ->from($row->foreign_table_name)
                        ->whereColumn(
                            "{$row->foreign_table_name}.{$row->foreign_column_name}",
                            "{$row->table_name}.{$row->column_name}",
                        );
                })
                ->count();

            if ($orphans > 0) {
                $violations[] = [
                    'table' => $row->table_name,
                    'column' => $row->column_name,
                    'orphans' => $orphans,
                ];
            }
        }

        return $violations;
    }

    /**
     * @return array<string, bool>
     */
    private function spotCheckIndexes(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $checks = [
            'users_public_id_unique' => $this->indexExists('users', 'users_public_id_unique'),
            'users_username_lower_unique' => $this->indexExists('users', 'users_username_lower_unique'),
            'charts_import_batch_id_foreign' => $this->constraintExists('charts', 'charts_import_batch_id_foreign'),
            'snippets_active_sip_cue_unique' => $this->indexExists('snippets', 'snippets_active_sip_cue_unique'),
        ];

        return $checks;
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
            ['public', $table, $index],
        );

        return $result !== null;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ?',
            ['public', $table, $constraint],
        );

        return $result !== null;
    }

    /**
     * @return list<string>
     */
    private function migrationOrder(): array
    {
        return DB::table('migrations')
            ->orderBy('id')
            ->pluck('migration')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTextReport(array $report): void
    {
        $this->info('CCMM Schema Validation Report');
        $this->line('Database: '.$report['database']);
        $this->line('Tables: '.$report['table_count']);
        $this->line('CCMM present: '.$report['ccmm_present'].' / '.$report['ccmm_expected']);

        if ($report['missing_tables'] !== []) {
            $this->error('Missing: '.implode(', ', $report['missing_tables']));
        }

        if ($report['unexpected_tables'] !== []) {
            $this->warn('Unexpected: '.implode(', ', $report['unexpected_tables']));
        }

        if ($report['forbidden_tables_present'] !== []) {
            $this->error('Forbidden present: '.implode(', ', $report['forbidden_tables_present']));
        }

        if ($report['fk_orphan_violations'] !== []) {
            $this->error('FK orphan violations detected');
        }

        $this->line('Index checks: '.json_encode($report['index_checks']));
        $this->line($report['passed'] ? 'PASS' : 'FAIL');
    }
}
