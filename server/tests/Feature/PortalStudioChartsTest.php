<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalStudioChartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal.band_id' => 1,
            'portal.library_chart_disk' => 'library',
        ]);
        Storage::fake('library');
    }

    public function test_studio_home_shows_charts_card_for_authenticated_musician(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Charts', false)
            ->assertSee('View your song charts', false)
            ->assertSee(route('studio.charts.index'), false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('Profile completeness', false)
            ->assertDontSee('Performance readiness', false)
            ->assertDontSee('completion', false)
            ->assertDontSee('practice', false);
    }

    public function test_unauthenticated_users_cannot_access_charts_index(): void
    {
        $this->get('/studio/charts')->assertRedirect('/');
    }

    public function test_charts_index_lists_songs_with_matching_chart_counts_only(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Blue Moon',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Chart'],
                ['part' => 'Bass', 'title' => 'Bass Chart'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('Blue Moon', false)
            ->assertSee('1 chart', false)
            ->assertDontSee('Bass Chart', false)
            ->assertDontSee('2 charts', false)
            ->assertSee(route('studio.charts.show', $song), false);
    }

    public function test_song_detail_shows_only_matching_charts(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $vocalsRef = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);
        $user->person->instruments()->attach($vocalsRef->id, ['is_primary' => false]);

        $song = $this->seedSongWithCharts(
            name: 'Night Train',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Lead'],
                ['part' => 'Vocals', 'title' => 'Vocal Chart'],
                ['part' => 'Drums', 'title' => 'Drum Chart'],
            ],
        );

        $this->actingAs($user)->get(route('studio.charts.show', $song))
            ->assertOk()
            ->assertSee('My Charts', false)
            ->assertSee('Trumpet Lead', false)
            ->assertSee('Vocal Chart', false)
            ->assertSee('Trumpet', false)
            ->assertSee('Vocals', false)
            ->assertDontSee('Drum Chart', false)
            ->assertDontSee('Drums', false);
    }

    public function test_chart_download_requires_authentication(): void
    {
        $chart = $this->seedSongWithCharts(
            name: 'Locked Chart Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Only'],
            ],
        )->charts()->firstOrFail();

        $this->get(route('studio.charts.file', $chart))->assertRedirect('/');
    }

    public function test_chart_download_is_forbidden_for_non_matching_instrument(): void
    {
        $user = User::factory()->create();
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $user->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Brass Only',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Only'],
            ],
        );

        $trumpetChart = Chart::query()->where('song_id', $song->id)->firstOrFail();

        $this->actingAs($user)->get(route('studio.charts.file', $trumpetChart))
            ->assertForbidden();
    }

    public function test_chart_download_serves_private_storage_for_matching_instrument(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Private File Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet PDF', 'filename' => 'trumpet-part.pdf'],
            ],
        );

        $chart = Chart::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('library')->put($chart->storage_reference, '%PDF-1.4 test chart bytes');

        $response = $this->actingAs($user)->get(route('studio.charts.file', $chart));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('%PDF-1.4 test chart bytes', $response->streamedContent());
        $this->assertStringNotContainsString('/storage/', $response->headers->get('content-disposition') ?? '');
    }

    public function test_empty_state_when_no_matching_charts_exist(): void
    {
        $user = User::factory()->create();
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $user->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $this->seedSongWithCharts(
            name: 'Brass Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Only'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('No matching charts are available for your instruments yet.', false)
            ->assertDontSee('Brass Song', false)
            ->assertDontSee('incomplete', false)
            ->assertDontSee('unprepared', false);
    }

    public function test_empty_state_when_person_has_no_instrument_assignments(): void
    {
        $user = User::factory()->create();

        $this->seedSongWithCharts(
            name: 'Any Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Only'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('No matching charts are available for your instruments yet.', false);
    }

    public function test_missing_chart_file_returns_not_found(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Missing File Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Missing Trumpet File'],
            ],
        );

        $chart = Chart::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('library')->delete($chart->storage_reference);

        $this->actingAs($user)->get(route('studio.charts.file', $chart))
            ->assertNotFound();
    }

    public function test_charts_index_does_not_500_when_library_tables_missing(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('song_instrument_parts');
        \Illuminate\Support\Facades\Schema::dropIfExists('charts');
        \Illuminate\Support\Facades\Schema::dropIfExists('instrument_parts');
        \Illuminate\Support\Facades\Schema::dropIfExists('songs');

        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('No matching charts are available for your instruments yet.', false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('completion', false)
            ->assertDontSee('practice', false);
    }

    public function test_charts_index_does_not_500_when_library_storage_root_missing(): void
    {
        config(['filesystems.disks.library.root' => '/tmp/esb-missing-library-root-'.uniqid()]);

        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $this->seedSongWithCharts(
            name: 'Storage Root Missing Song',
            chartSetups: [
                ['part' => 'Trumpet', 'title' => 'Trumpet Chart'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('Storage Root Missing Song', false);
    }

    public function test_chart_file_returns_not_found_when_library_tables_missing(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('song_instrument_parts');
        \Illuminate\Support\Facades\Schema::dropIfExists('charts');
        \Illuminate\Support\Facades\Schema::dropIfExists('instrument_parts');
        \Illuminate\Support\Facades\Schema::dropIfExists('songs');

        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio/charts/files/1')
            ->assertNotFound();
    }

    public function test_alto_sax_musician_sees_alto_sax_songs_in_charts_index(): void
    {
        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Moondance',
            chartSetups: [
                ['part' => 'Alto Sax', 'title' => 'Moondance Alto Sax'],
                ['part' => 'Tenor Sax', 'title' => 'Moondance Tenor Sax'],
                ['part' => 'Trumpet', 'title' => 'Moondance Trumpet'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('Moondance', false)
            ->assertSee('1 chart', false)
            ->assertDontSee('Moondance Tenor Sax', false)
            ->assertDontSee('Moondance Trumpet', false)
            ->assertSee(route('studio.charts.show', $song), false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('completion', false);
    }

    public function test_alto_sax_musician_sees_alto_sax_chart_on_song_detail(): void
    {
        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Take Five',
            chartSetups: [
                ['part' => 'Alto Sax', 'title' => 'Take Five Alto'],
                ['part' => 'Guitar', 'title' => 'Take Five Guitar'],
                ['part' => 'Bass', 'title' => 'Take Five Bass'],
                ['part' => 'Drums', 'title' => 'Take Five Drums'],
            ],
        );

        $this->actingAs($user)->get(route('studio.charts.show', $song))
            ->assertOk()
            ->assertSee('My Charts', false)
            ->assertSee('Take Five Alto', false)
            ->assertSee('Alto Sax', false)
            ->assertDontSee('Take Five Guitar', false)
            ->assertDontSee('Take Five Bass', false)
            ->assertDontSee('Take Five Drums', false);
    }

    public function test_alto_sax_musician_can_download_alto_sax_chart(): void
    {
        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Alto Feature',
            chartSetups: [
                ['part' => 'Alto Sax', 'title' => 'Alto Feature Chart', 'filename' => 'alto_sax.pdf'],
            ],
        );

        $chart = Chart::query()->where('song_id', $song->id)->firstOrFail();
        Storage::disk('library')->put($chart->storage_reference, '%PDF-1.4 alto sax chart');

        $this->actingAs($user)->get(route('studio.charts.file', $chart))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_alto_sax_musician_cannot_download_other_instrument_charts(): void
    {
        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Mixed Brass',
            chartSetups: [
                ['part' => 'Tenor Sax', 'title' => 'Tenor Only'],
                ['part' => 'Trumpet', 'title' => 'Trumpet Only'],
                ['part' => 'Guitar', 'title' => 'Guitar Only'],
            ],
        );

        foreach (Chart::query()->where('song_id', $song->id)->get() as $chart) {
            Storage::disk('library')->put($chart->storage_reference, '%PDF-1.4 blocked');
            $this->actingAs($user)->get(route('studio.charts.file', $chart))->assertForbidden();
        }
    }

    public function test_alto_sax_musician_matches_eb_variant_catalog_part_name(): void
    {
        $user = User::factory()->create();
        $altoSaxRef = InstrumentReference::query()->where('slug', 'scaffold-alto-sax')->firstOrFail();
        $user->person->instruments()->attach($altoSaxRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts(
            name: 'Eb Alto Song',
            chartSetups: [
                ['part' => 'Alto Sax in Eb', 'title' => 'Eb Alto Chart'],
                ['part' => 'Tenor Sax', 'title' => 'Tenor Chart'],
            ],
        );

        $this->actingAs($user)->get('/studio/charts')
            ->assertOk()
            ->assertSee('Eb Alto Song', false)
            ->assertSee('1 chart', false);

        $this->actingAs($user)->get(route('studio.charts.show', $song))
            ->assertOk()
            ->assertSee('Eb Alto Chart', false)
            ->assertDontSee('Tenor Chart', false);
    }

    /**
     * @param  list<array{part: string, title: string, filename?: string}>  $chartSetups
     */
    private function seedSongWithCharts(string $name, array $chartSetups): Song
    {
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '001',
            'name' => $name,
        ]);

        foreach ($chartSetups as $index => $setup) {
            $part = InstrumentPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'band_id' => 1,
                'name' => $setup['part'],
                'active' => true,
            ]);

            $filename = $setup['filename'] ?? strtolower(str_replace(' ', '-', $setup['part'])).'.pdf';
            $storageReference = 'charts/1/001/'.strtolower(str_replace(' ', '-', $setup['part'])).'-'.$index.'.pdf';

            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $setup['title'],
                'original_filename' => $filename,
                'storage_reference' => $storageReference,
                'checksum' => hash('sha256', $storageReference),
                'mime_type' => 'application/pdf',
                'file_size' => 128,
            ]);

            SongInstrumentPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'instrument_part_id' => $part->id,
                'chart_id' => $chart->id,
            ]);
        }

        return $song->fresh(['charts']);
    }
}
