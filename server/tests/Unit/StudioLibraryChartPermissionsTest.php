<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StudioLibraryChartPermissionsTest extends TestCase
{
    public function test_normalize_command_sets_traversable_directory_permissions(): void
    {
        $dir = storage_path('app/library/charts/9/099');
        File::ensureDirectoryExists($dir);
        chmod($dir, 0700);
        file_put_contents($dir.'/test.pdf', '%PDF-1.4');
        chmod($dir.'/test.pdf', 0600);

        $this->artisan('studio:normalize-library-chart-permissions')->assertSuccessful();

        $this->assertSame(0755, fileperms($dir) & 0777);
        $this->assertSame(0644, fileperms($dir.'/test.pdf') & 0777);
    }
}
