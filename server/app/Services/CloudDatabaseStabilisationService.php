<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class CloudDatabaseStabilisationService
{
    /** @var list<string> */
    public const CCMM_TABLES = [
        'bands', 'users', 'people', 'person_secure_fields', 'person_files', 'person_iem_settings',
        'person_instruments', 'musicians', 'musician_band_roles', 'instrument_reference',
        'song_moods', 'time_signatures', 'musical_keys', 'songs', 'cues', 'instrument_parts',
        'import_batches', 'import_entity_mappings', 'charts', 'song_instrument_parts', 'snippets',
        'action_types', 'action_definitions', 'action_parameters', 'cue_actions',
        'ableton_show_files', 'shows', 'show_playlist_items', 'performances', 'performance_assignments',
        'devices', 'capabilities', 'assignments', 'venues', 'festivals',
        'effect_package_types', 'effects', 'effect_parameters', 'effect_definitions',
        'effect_packages', 'effect_package_items', 'effect_package_item_parameters',
        'effect_package_item_target_sections', 'song_effect_assignments', 'show_console_baselines',
        'mix_moves', 'person_invitations', 'cloud_recovery_entity_map',
    ];

    /** @var list<string> */
    private const SYNC_ORDER = [
        'song_moods', 'time_signatures', 'musical_keys',
        'instrument_parts', 'songs', 'cues', 'import_batches', 'import_entity_mappings',
        'charts', 'song_instrument_parts', 'snippets',
        'action_types', 'action_definitions', 'action_parameters', 'cue_actions',
        'ableton_show_files', 'shows', 'show_playlist_items', 'performances', 'performance_assignments',
        'venues', 'festivals',
        'effect_package_types', 'effects', 'effect_parameters', 'effect_definitions',
        'effect_packages', 'effect_package_items', 'effect_package_item_parameters',
        'effect_package_item_target_sections', 'song_effect_assignments', 'show_console_baselines',
        'musicians', 'musician_band_roles', 'devices', 'capabilities', 'assignments',
    ];

    /** @var list<string> */
    private const PRESERVE_TABLES = [
        'bands', 'users', 'people', 'invite_links', 'invite_link_acceptances', 'instrument_reference',
    ];

    /**
     * @return array{source: string, target: string, missing_on_target: list<string>, missing_on_source: list<string>, target_counts: array<string, int>, source_counts: array<string, int>}
     */
    public function compare(string $sourceConnection, string $targetConnection): array
    {
        $missingOnTarget = [];
        $missingOnSource = [];

        foreach (self::CCMM_TABLES as $table) {
            $sourceHas = Schema::connection($sourceConnection)->hasTable($table);
            $targetHas = Schema::connection($targetConnection)->hasTable($table);

            if ($sourceHas && ! $targetHas) {
                $missingOnTarget[] = $table;
            }

            if ($targetHas && ! $sourceHas) {
                $missingOnSource[] = $table;
            }
        }

        return [
            'source' => $this->connectionLabel($sourceConnection),
            'target' => $this->connectionLabel($targetConnection),
            'missing_on_target' => $missingOnTarget,
            'missing_on_source' => $missingOnSource,
            'target_counts' => $this->counts($targetConnection, self::CCMM_TABLES),
            'source_counts' => $this->counts($sourceConnection, self::CCMM_TABLES),
        ];
    }

    /**
     * @return array{inserted: int, skipped: int, migration_files: list<string>}
     */
    public function markExistingCcmmMigrations(string $connection, bool $dryRun = false): array
    {
        $files = $this->ccmmMigrationFiles();
        $inserted = 0;
        $skipped = 0;
        $marked = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (DB::connection($connection)->table('migrations')->where('migration', $name)->exists()) {
                $skipped++;

                continue;
            }

            if ($name === '2026_06_25_001400_cloud_stabilisation_additive') {
                continue;
            }

            if (! $dryRun) {
                DB::connection($connection)->table('migrations')->insert([
                    'migration' => $name,
                    'batch' => 9001,
                ]);
            }

            $inserted++;
            $marked[] = $name;
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'migration_files' => $marked,
        ];
    }

    /**
     * @return array<string, array{inserted: int, skipped: int, preserved: int}>
     */
    public function syncData(string $sourceConnection, string $targetConnection, bool $dryRun = false): array
    {
        $summary = [];

        foreach (self::SYNC_ORDER as $table) {
            if (! Schema::connection($sourceConnection)->hasTable($table)
                || ! Schema::connection($targetConnection)->hasTable($table)) {
                continue;
            }

            $preserve = in_array($table, self::PRESERVE_TABLES, true);
            $targetCount = (int) DB::connection($targetConnection)->table($table)->count();
            $rows = DB::connection($sourceConnection)->table($table)->orderBy('id')->get();

            if ($rows->isEmpty()) {
                $summary[$table] = ['inserted' => 0, 'skipped' => 0, 'preserved' => $targetCount];

                continue;
            }

            $inserted = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $payload = (array) $row;
                $exists = $this->rowExists($targetConnection, $table, $payload);

                if ($preserve && ($targetCount > 0 || $exists)) {
                    $skipped++;

                    continue;
                }

                if ($exists) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    DB::connection($targetConnection)->table($table)->insert($payload);
                    $inserted++;
                } else {
                    $inserted++;
                }
            }

            if (! $dryRun && $inserted > 0) {
                $this->refreshSequence($targetConnection, $table);
            }

            $summary[$table] = [
                'inserted' => $inserted,
                'skipped' => $skipped,
                'preserved' => $preserve ? $targetCount : 0,
            ];
        }

        return $summary;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    public function counts(string $connection, array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = Schema::connection($connection)->hasTable($table)
                ? (int) DB::connection($connection)->table($table)->count()
                : -1;
        }

        return $counts;
    }

    /** @return list<string> */
    private function ccmmMigrationFiles(): array
    {
        require_once dirname(base_path()).'/database/ccmm_migration_paths.php';

        $files = [];

        foreach (ccmm_migration_paths() as $path) {
            foreach (File::glob($path.'/*.php') ?: [] as $file) {
                $files[] = $file;
            }
        }

        sort($files);

        return $files;
    }

    /** @param  array<string, mixed>  $payload */
    private function rowExists(string $connection, string $table, array $payload): bool
    {
        $query = DB::connection($connection)->table($table);

        if (isset($payload['public_id']) && is_string($payload['public_id']) && $payload['public_id'] !== '') {
            return $query->where('public_id', $payload['public_id'])->exists();
        }

        if (isset($payload['id'])) {
            return DB::connection($connection)->table($table)->where('id', $payload['id'])->exists();
        }

        return false;
    }

    private function refreshSequence(string $connection, string $table): void
    {
        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($connection)->statement(
            "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))"
        );
    }

    private function connectionLabel(string $connection): string
    {
        $config = config('database.connections.'.$connection, []);

        return sprintf(
            '%s@%s:%s/%s',
            $config['username'] ?? '?',
            $config['host'] ?? '?',
            $config['port'] ?? '?',
            $config['database'] ?? '?',
        );
    }
}
