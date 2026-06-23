<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StudioLibraryPromoteIncomingChartsCommand extends Command
{
    protected $signature = 'studio:library-promote-incoming';

    protected $description = 'Promote chart PDFs from storage/app/library/incoming into charts/';

    public function handle(): int
    {
        $root = storage_path('app/library');
        $incoming = $root.'/incoming';
        $charts = $root.'/charts';
        $archive = $incoming.'/charts.tar.gz';

        if (! File::isDirectory($incoming)) {
            $this->warn('No incoming directory present.');

            return self::SUCCESS;
        }

        if (File::exists($archive)) {
            $this->info('Extracting incoming/charts.tar.gz …');
            $process = new \Symfony\Component\Process\Process([
                'tar', '-xzf', $archive, '-C', $root,
            ]);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error($process->getErrorOutput());

                return self::FAILURE;
            }

            File::delete($archive);
        }

        $incomingCharts = $incoming.'/charts';

        if (File::isDirectory($incomingCharts)) {
            $this->info('Promoting incoming/charts/ …');
            File::ensureDirectoryExists($charts);

            foreach (File::allFiles($incomingCharts) as $file) {
                $relative = $file->getRelativePathname();
                $target = $charts.'/'.$relative;
                File::ensureDirectoryExists(dirname($target));
                File::copy($file->getPathname(), $target);
            }

            File::deleteDirectory($incomingCharts);
        }

        $this->info('Incoming chart promotion complete.');

        return self::SUCCESS;
    }
}
