<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StudioNormalizeLibraryChartPermissionsCommand extends Command
{
    protected $signature = 'studio:normalize-library-chart-permissions';

    protected $description = 'Ensure chart PDF directories are traversable by the PHP-FPM user';

    public function handle(): int
    {
        $chartsRoot = storage_path('app/library/charts');

        if (! File::isDirectory($chartsRoot)) {
            $this->warn('No charts directory present.');

            return self::SUCCESS;
        }

        $directories = 0;
        $files = 0;

        foreach (File::allFiles($chartsRoot) as $file) {
            if (@chmod($file->getPathname(), 0644)) {
                $files++;
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($chartsRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item->isDir()) {
                continue;
            }

            if (@chmod($item->getPathname(), 0755)) {
                $directories++;
            }
        }

        if (@chmod($chartsRoot, 0755)) {
            $directories++;
        }

        $this->info("Normalized chart permissions ({$directories} directories, {$files} files).");

        return self::SUCCESS;
    }
}
