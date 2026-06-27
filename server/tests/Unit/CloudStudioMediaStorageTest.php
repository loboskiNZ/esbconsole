<?php

namespace Tests\Unit;

use App\Support\CloudStudioMediaStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudStudioMediaStorageTest extends TestCase
{
    public function test_chart_disk_relative_path_when_root_includes_charts_segment(): void
    {
        config([
            'portal.library_storage_root' => '/srv/storage/app/library/charts',
        ]);

        Storage::fake('library');

        $storage = app(CloudStudioMediaStorage::class);

        $this->assertSame(
            '1/031/alto_sax.pdf',
            $storage->chartDiskRelativePath('charts/1/031/alto_sax.pdf'),
        );

        $this->assertSame(
            '1/031/alto_sax.pdf',
            $storage->legacyLocalRelativePath('library/charts/1/031/alto_sax.pdf'),
        );
    }

    public function test_put_local_creates_directory_before_write(): void
    {
        Storage::fake('library');

        $storage = app(CloudStudioMediaStorage::class);

        $storage->putLocal('charts/1/099/new-chart.pdf', '%PDF-1.4 test');

        Storage::disk('library')->assertExists('charts/1/099/new-chart.pdf');
    }
}
