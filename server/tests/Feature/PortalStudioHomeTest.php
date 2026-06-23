<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\User;
use App\Services\StudioChartSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalStudioHomeTest extends TestCase
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

    public function test_studio_hero_renders_primary_actions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('Welcome to Studio', false)
            ->assertSee('esb-studio__hero', false)
            ->assertSee('All Charts', false)
            ->assertSee('Your rehearsal library', false)
            ->assertSee('Shows', false)
            ->assertSee('Schedule', false)
            ->assertSee('Upcoming shows', false)
            ->assertSee('Rehearsals schedule', false)
            ->assertSee('Search songs', false)
            ->assertSee('Band notices', false)
            ->assertDontSee('View your song charts', false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('Profile completeness', false)
            ->assertDontSee('Performance readiness', false)
            ->assertDontSee('completion', false)
            ->assertDontSee('practice', false)
            ->assertDontSee('evaluation', false);
    }

    public function test_hero_counts_reflect_musician_accessible_library(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $this->seedSongWithCharts('Electronic Love', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Chart'],
            ['part' => 'Bass', 'title' => 'Bass Chart'],
        ], '001');

        $this->seedSongWithCharts('Night Drive', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Night'],
        ], '002');

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('2 Songs', false)
            ->assertSee('2 Charts', false);
    }

    public function test_hero_counts_exclude_inaccessible_charts(): void
    {
        $user = User::factory()->create();
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $user->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $this->seedSongWithCharts('Brass Only Song', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Chart'],
        ]);

        $this->actingAs($user)->get('/studio')
            ->assertOk()
            ->assertSee('0 Songs', false)
            ->assertSee('0 Charts', false);
    }

    public function test_chart_search_returns_matching_accessible_songs(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $vocalsRef = InstrumentReference::query()->where('slug', 'scaffold-vocals')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);
        $user->person->instruments()->attach($vocalsRef->id, ['is_primary' => false]);

        $love = $this->seedSongWithCharts('Electronic Love', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Love'],
            ['part' => 'Vocals', 'title' => 'Vocal Love'],
        ], '011');
        $this->seedSongWithCharts('Electronic Dreams', [
            ['part' => 'Vocals', 'title' => 'Vocal Dreams'],
        ], '012');
        $this->seedSongWithCharts('Electric Avenue', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Avenue'],
        ], '013');
        $this->seedSongWithCharts('Brass Tower', [
            ['part' => 'Trumpet', 'title' => 'Brass Only'],
        ], '014');

        $response = $this->actingAs($user)->getJson(route('studio.charts.search', ['q' => 'electronic']));

        $response->assertOk();
        $response->assertJsonCount(2, 'results');
        $response->assertJsonFragment(['name' => 'Electronic Love', 'parts' => ['Trumpet', 'Vocals']]);
        $response->assertJsonFragment(['name' => 'Electronic Dreams', 'parts' => ['Vocals']]);
        $response->assertJsonMissing(['name' => 'Brass Tower']);
        $this->assertContains(
            route('studio.charts.show', $love),
            collect($response->json('results'))->pluck('url')->all(),
        );
    }

    public function test_chart_search_excludes_inaccessible_songs(): void
    {
        $user = User::factory()->create();
        $keysRef = InstrumentReference::query()->where('slug', 'scaffold-keys')->firstOrFail();
        $user->person->instruments()->attach($keysRef->id, ['is_primary' => true]);

        $this->seedSongWithCharts('Electronic Love', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Love'],
        ]);

        $this->actingAs($user)->getJson(route('studio.charts.search', ['q' => 'electronic']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_chart_search_supports_partial_and_case_insensitive_matching(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts('Electronic Love', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Love'],
        ]);

        $this->actingAs($user)->getJson(route('studio.charts.search', ['q' => 'ELECTRONIC LO']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.song_id', $song->id);

        $this->actingAs($user)->getJson(route('studio.charts.search', ['q' => 'ele']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Electronic Love']);
    }

    public function test_chart_search_result_url_opens_song_detail(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithCharts('Electronic Love', [
            ['part' => 'Trumpet', 'title' => 'Trumpet Love'],
        ]);

        $payload = $this->actingAs($user)->getJson(route('studio.charts.search', ['q' => 'electronic love']))
            ->assertOk()
            ->json('results.0');

        $this->actingAs($user)->get($payload['url'])
            ->assertOk()
            ->assertSee('Electronic Love', false)
            ->assertSee('Trumpet Love', false);
    }

    public function test_unauthenticated_users_cannot_search_charts(): void
    {
        $this->getJson(route('studio.charts.search', ['q' => 'electronic']))
            ->assertUnauthorized();
    }

    public function test_search_service_token_matching_is_case_insensitive(): void
    {
        $service = app(StudioChartSearchService::class);

        $this->assertTrue($service->matchesQuery('Electronic Love', 'electronic'));
        $this->assertTrue($service->matchesQuery('Electronic Love', 'ELECTRONIC LO'));
        $this->assertFalse($service->matchesQuery('Blue Moon', 'electronic'));
    }

    /**
     * @param  list<array{part: string, title: string}>  $chartSetups
     */
    private function seedSongWithCharts(string $name, array $chartSetups, string $songCode = '001'): Song
    {
        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => $songCode,
            'name' => $name,
        ]);

        foreach ($chartSetups as $index => $setup) {
            $part = InstrumentPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'band_id' => 1,
                'name' => $setup['part'],
                'active' => true,
            ]);

            $storageReference = 'charts/1/'.$songCode.'/'.strtolower(str_replace(' ', '-', $setup['part'])).'-'.$index.'.pdf';

            $chart = Chart::query()->create([
                'public_id' => (string) Str::uuid(),
                'song_id' => $song->id,
                'title' => $setup['title'],
                'original_filename' => strtolower(str_replace(' ', '-', $setup['part'])).'.pdf',
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

        return $song->fresh();
    }
}
