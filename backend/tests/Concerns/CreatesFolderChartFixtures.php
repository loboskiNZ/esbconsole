<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

trait CreatesFolderChartFixtures
{
    protected function createFolderChartFixture(string $basePath, array $tree): void
    {
        File::ensureDirectoryExists($basePath);

        foreach ($tree as $songFolder => $files) {
            $songPath = $basePath.DIRECTORY_SEPARATOR.$songFolder;
            File::ensureDirectoryExists($songPath);

            foreach ($files as $filename => $contents) {
                File::put($songPath.DIRECTORY_SEPARATOR.$filename, $contents);
            }
        }
    }

    protected function minimalPdfContents(string $label = 'chart'): string
    {
        return "%PDF-1.4\n% {$label}\n";
    }
}
