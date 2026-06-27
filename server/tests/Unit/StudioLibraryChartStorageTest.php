<?php

namespace Tests\Unit;

use App\Support\StudioLibraryChartStorage;
use Tests\TestCase;

class StudioLibraryChartStorageTest extends TestCase
{
    public function test_resolves_storage_reference_under_library_root_once(): void
    {
        config([
            'portal.library_storage_root' => '/srv/storage/app/library',
        ]);

        $storage = app(StudioLibraryChartStorage::class);

        $this->assertSame(
            '/srv/storage/app/library/charts/1/031/alto_sax.pdf',
            $storage->absolutePath('charts/1/031/alto_sax.pdf'),
        );
    }

    public function test_does_not_double_join_when_disk_root_includes_charts(): void
    {
        config([
            'portal.library_storage_root' => '/srv/storage/app/library/charts',
        ]);

        $storage = app(StudioLibraryChartStorage::class);

        $this->assertSame(
            '1/031/alto_sax.pdf',
            $storage->diskRelativePath('charts/1/031/alto_sax.pdf'),
        );

        $this->assertSame(
            '/srv/storage/app/library/charts/1/031/alto_sax.pdf',
            $storage->absolutePath('charts/1/031/alto_sax.pdf'),
        );
    }
}
