<?php

namespace App\Console\Commands;

use App\Services\StudioLibrarySyncService;
use Illuminate\Console\Command;

class StudioLibrarySyncCommand extends Command
{
    protected $signature = 'studio:library-sync
                            {--source=library_source : Source database connection name}
                            {--target=library : Target database connection name}';

    protected $description = 'Sync governed library tables (InstrumentParts, Songs, Charts, SongInstrumentParts) into the portal library connection';

    public function handle(StudioLibrarySyncService $syncService): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');

        $this->info("Syncing library from [{$source}] to [{$target}]…");

        try {
            $counts = $syncService->sync($source, $target);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-24s %d', $table, $count));
        }

        $this->info('Library sync complete.');

        return self::SUCCESS;
    }
}
