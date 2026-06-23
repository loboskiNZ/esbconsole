<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\User;
use App\Services\StudioLibrarySyncService;
use App\Support\StudioLibraryAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioLibraryConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('portal.band_id', 1);
        Config::set('portal.library_chart_disk', 'library');
        Storage::fake('library');
    }

    public function test_portal_models_read_configured_library_connection(): void
    {
        Config::set('portal.library_connection', 'sqlite');

        Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '001',
            'name' => 'Connected Song',
        ]);

        $this->assertTrue(app(StudioLibraryAvailability::class)->isAvailable());
        $this->assertSame(1, Song::query()->count());
        $this->assertSame('Connected Song', Song::query()->value('name'));
    }

    public function test_missing_library_tables_on_configured_connection_does_not_500_charts_index(): void
    {
        Schema::dropIfExists('song_instrument_parts');
        Schema::dropIfExists('charts');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('instrument_parts');

        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => false]);

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('No matching charts are available for your instruments yet.', false);
    }

    public function test_library_sync_upserts_rows_into_target_connection(): void
    {
        $sourcePath = database_path('testing-library-source.sqlite');
        $targetPath = database_path('testing-library-target.sqlite');

        foreach ([$sourcePath, $targetPath] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
            touch($path);
        }

        $sourceConfig = array_merge(config('database.connections.sqlite'), ['database' => $sourcePath]);
        $targetConfig = array_merge(config('database.connections.sqlite'), ['database' => $targetPath]);

        Config::set('database.connections.library_source', $sourceConfig);
        Config::set('database.connections.library', $targetConfig);

        $this->artisan('migrate:fresh', [
            '--database' => 'library_source',
            '--force' => true,
        ]);
        $this->artisan('migrate:fresh', [
            '--database' => 'library',
            '--force' => true,
        ]);

        DB::connection('library_source')->table('instrument_parts')->insert([
            'id' => 23,
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Alto Sax',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('library_source')->table('songs')->insert([
            'id' => 50,
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '031',
            'name' => 'Sync Song',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('library_source')->table('charts')->insert([
            'id' => 90,
            'public_id' => (string) Str::uuid(),
            'song_id' => 50,
            'title' => 'Sync Alto Chart',
            'storage_reference' => 'charts/1/031/alto_sax.pdf',
            'checksum' => 'abc123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('library_source')->table('song_instrument_parts')->insert([
            'id' => 100,
            'public_id' => (string) Str::uuid(),
            'song_id' => 50,
            'instrument_part_id' => 23,
            'chart_id' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counts = app(StudioLibrarySyncService::class)->sync('library_source', 'library');

        $this->assertSame(1, $counts['instrument_parts']);
        $this->assertSame(1, $counts['songs']);
        $this->assertSame(1, $counts['charts']);
        $this->assertSame(1, $counts['song_instrument_parts']);
        $this->assertSame('Sync Song', DB::connection('library')->table('songs')->value('name'));

        unlink($sourcePath);
        unlink($targetPath);
    }

    public function test_non_primary_assigned_instruments_are_included_for_chart_matching(): void
    {
        $user = User::factory()->create();
        $drumsRef = InstrumentReference::query()->where('slug', 'scaffold-drums')->firstOrFail();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($drumsRef->id, ['is_primary' => true]);
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => false]);

        $partAlto = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Alto Sax',
            'active' => true,
        ]);
        $partDrums = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Drums',
            'active' => true,
        ]);

        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '031',
            'name' => 'Multi Part Song',
        ]);

        foreach ([
            [$partAlto, 'Alto Chart'],
            [$partDrums, 'Drum Chart'],
        ] as [$part, $title]) {
            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $title,
                'storage_reference' => 'charts/1/031/'.Str::slug($title).'.pdf',
                'checksum' => hash('sha256', $title),
            ]);

            SongInstrumentPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'instrument_part_id' => $part->id,
                'chart_id' => $chart->id,
            ]);
        }

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('Multi Part Song', false)
            ->assertSee('2 charts', false);

        $this->actingAs($user)->get(route('studio.charts.show', $song))
            ->assertOk()
            ->assertSee('Alto Chart', false)
            ->assertSee('Drum Chart', false);
    }
}
