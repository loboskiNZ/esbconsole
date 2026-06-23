<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudioLibrarySyncService
{
    /** @var list<string> */
    private const SYNC_TABLES = [
        'song_moods',
        'time_signatures',
        'musical_keys',
        'instrument_parts',
        'songs',
        'charts',
        'song_instrument_parts',
    ];

    /**
     * @return array<string, int>
     */
    public function sync(string $sourceConnection, string $targetConnection): array
    {
        $this->assertLibraryTablesExist($sourceConnection, 'source');
        $this->assertLibraryTablesExist($targetConnection, 'target');

        $counts = [];

        foreach (self::SYNC_TABLES as $table) {
            $rows = DB::connection($sourceConnection)
                ->table($table)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                $counts[$table] = 0;

                continue;
            }

            $payload = $rows
                ->map(fn ($row) => (array) $row)
                ->all();

            $updateColumns = array_values(array_diff(array_keys($payload[0]), ['id']));

            DB::connection($targetConnection)
                ->table($table)
                ->upsert($payload, ['id'], $updateColumns);

            $this->refreshSequence($targetConnection, $table);

            $counts[$table] = count($payload);
        }

        return $counts;
    }

    private function assertLibraryTablesExist(string $connection, string $label): void
    {
        foreach (self::SYNC_TABLES as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new \RuntimeException("Missing {$table} on {$label} connection [{$connection}].");
            }
        }
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

    /**
     * @return array<string, int>
     */
    public function counts(string $connection): array
    {
        $counts = [];

        foreach (self::SYNC_TABLES as $table) {
            $counts[$table] = Schema::connection($connection)->hasTable($table)
                ? (int) DB::connection($connection)->table($table)->count()
                : 0;
        }

        return $counts;
    }
}
