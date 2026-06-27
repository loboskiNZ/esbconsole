<?php

namespace Tests\Unit;

use App\Models\Band;
use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\Person;
use App\Models\User;
use App\Services\CloudStudioMediaMigrationService;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLibrarySchema;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class CloudStudioMediaMigrationServiceTest extends TestCase
{
    use CreatesLibrarySchema;
    use EnsuresPortalBand;
    use RefreshDatabase;

    private CloudStudioMediaMigrationService $migration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
            'portal.library_connection' => 'sqlite',
        ]);

        $this->ensurePortalBand();
        $this->createLibrarySchema();

        Storage::fake('media');
        Storage::fake('library');
        Storage::fake('local');

        $this->migration = app(CloudStudioMediaMigrationService::class);
    }

    public function test_copies_legacy_local_chart_to_s3_and_updates_database_reference(): void
    {
        $chart = $this->createChart('charts/1/010/trumpet.pdf');
        Storage::disk('library')->put('charts/1/010/trumpet.pdf', '%PDF-1.4 legacy');

        $row = $this->migration->migrateEntry($this->chartEntry($chart));

        $chart->refresh();
        $this->assertSame('copied', $row['s3_copy_status']);
        $this->assertSame('updated', $row['db_update_status']);
        $this->assertSame('library/charts/1/010/trumpet.pdf', $chart->storage_reference);
        Storage::disk('media')->assertExists('library/charts/1/010/trumpet.pdf');
        Storage::disk('library')->assertExists('charts/1/010/trumpet.pdf');
    }

    public function test_second_run_skips_copy_when_s3_object_already_present(): void
    {
        $chart = $this->createChart('charts/1/011/trumpet.pdf');
        Storage::disk('library')->put('charts/1/011/trumpet.pdf', '%PDF-1.4 legacy');

        $this->migration->migrateEntry($this->chartEntry($chart));
        $row = $this->migration->migrateEntry($this->chartEntry($chart->fresh()));

        $this->assertSame('already_present', $row['s3_copy_status']);
        $this->assertSame('already_canonical', $row['db_update_status']);
    }

    public function test_failed_copy_leaves_database_reference_unchanged(): void
    {
        $chart = $this->createChart('charts/1/012/missing.pdf');

        $row = $this->migration->migrateEntry($this->chartEntry($chart));

        $chart->refresh();
        $this->assertSame('failed', $row['s3_copy_status']);
        $this->assertSame('unchanged', $row['db_update_status']);
        $this->assertSame('charts/1/012/missing.pdf', $chart->storage_reference);
    }

    public function test_copies_profile_photo_and_band_asset_to_s3(): void
    {
        $user = User::factory()->create();
        $person = $user->person;
        $original = 'portal/profile-photos/'.$person->id.'/original.jpg';
        $display = 'portal/profile-photos/'.$person->id.'/display.jpg';
        $person->update([
            'profile_photo_path' => $original,
            'profile_photo_display_path' => $display,
        ]);
        Storage::disk('local')->put($original, 'original-bytes');
        Storage::disk('local')->put($display, 'display-bytes');

        $band = Band::query()->findOrFail(1);
        $logo = 'portal/band-assets/1/logo-legacy.png';
        $band->update(['logo_path' => $logo]);
        Storage::disk('local')->put($logo, 'logo-bytes');

        $rows = $this->migration->discover()
            ->map(fn (array $entry) => $this->migration->migrateEntry($entry))
            ->keyBy('media_type');

        $this->assertSame('copied', $rows['profile_photo']['s3_copy_status']);
        $this->assertSame('copied', $rows['profile_photo_display']['s3_copy_status']);
        $this->assertSame('copied', $rows['band_logo']['s3_copy_status']);
        Storage::disk('media')->assertExists($original);
        Storage::disk('media')->assertExists($display);
        Storage::disk('media')->assertExists($logo);
        Storage::disk('local')->assertExists($original);
    }

    public function test_migrated_and_legacy_files_remain_servable(): void
    {
        $chart = $this->createChart('charts/1/013/trumpet.pdf');
        Storage::disk('library')->put('charts/1/013/trumpet.pdf', '%PDF-1.4 legacy');

        $row = $this->migration->migrateEntry($this->chartEntry($chart));
        $mediaStorage = app(CloudStudioMediaStorage::class);

        $this->assertTrue($row['serves_via_s3']);
        $this->assertTrue($row['serves_via_local']);
        $this->assertTrue($mediaStorage->exists($chart->fresh()->storage_reference));
    }

    private function createChart(string $storageReference): Chart
    {
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '010',
            'name' => 'Migration Song',
        ]);

        return Chart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'title' => 'Trumpet',
            'original_filename' => 'trumpet.pdf',
            'storage_reference' => $storageReference,
            'checksum' => hash('sha256', 'test'),
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);
    }

    /**
     * @return array{
     *     media_type: string,
     *     table: string,
     *     record_id: int,
     *     column: string,
     *     storage_reference: string,
     * }
     */
    private function chartEntry(Chart $chart): array
    {
        return [
            'media_type' => 'chart',
            'table' => 'charts',
            'record_id' => (int) $chart->id,
            'column' => 'storage_reference',
            'storage_reference' => (string) $chart->storage_reference,
        ];
    }
}
