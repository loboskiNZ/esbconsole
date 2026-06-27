<?php

namespace Tests\Feature;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesLibrarySchema;
use Tests\Concerns\EnsuresPortalBand;
use Tests\TestCase;

class MigrateCloudStudioMediaToS3CommandTest extends TestCase
{
    use CreatesLibrarySchema;
    use EnsuresPortalBand;
    use RefreshDatabase;

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
    }

    public function test_command_writes_manifest_and_summary_report(): void
    {
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '020',
            'name' => 'Command Song',
        ]);

        Chart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'title' => 'Keys',
            'original_filename' => 'keys.pdf',
            'storage_reference' => 'charts/1/020/keys.pdf',
            'checksum' => hash('sha256', 'keys'),
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        Storage::disk('library')->put('charts/1/020/keys.pdf', '%PDF-1.4 keys');

        $manifest = storage_path('app/media-migration/test-manifest.jsonl');

        $exitCode = Artisan::call('studio:migrate-media-to-s3', [
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($manifest);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($manifest))));
        $this->assertNotEmpty($lines);

        $row = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('copied', $row['s3_copy_status']);
        $this->assertSame('updated', $row['db_update_status']);

        $summaryPath = storage_path('app/media-migration/test-manifest-summary.json');
        $this->assertFileExists($summaryPath);

        $summary = json_decode((string) file_get_contents($summaryPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $summary['summary']['discovered']);
        $this->assertSame(1, $summary['summary']['copied']);
        $this->assertFalse($summary['notes']['local_originals_deleted']);
    }

    public function test_dry_run_does_not_copy_or_update_database(): void
    {
        $user = User::factory()->create();
        $path = 'portal/profile-photos/'.$user->person_id.'/display.jpg';
        $user->person->update(['profile_photo_display_path' => $path]);
        Storage::disk('local')->put($path, 'display-bytes');

        $manifest = storage_path('app/media-migration/test-dry-run.jsonl');

        Artisan::call('studio:migrate-media-to-s3', [
            '--dry-run' => true,
            '--manifest' => $manifest,
        ]);

        Storage::disk('media')->assertMissing($path);
        $this->assertSame($path, $user->person->fresh()->profile_photo_display_path);
    }
}
