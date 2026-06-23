<?php

namespace Tests\Feature;

use App\Models\InstrumentReference;
use App\Models\Library\Chart;
use App\Models\Library\InstrumentPart;
use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Models\User;
use App\Support\StudioSongMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalStudioSongMetadataTest extends TestCase
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

    public function test_studio_musician_can_view_song_metadata(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithMetadata([
            'name' => 'Studio Metadata Song',
            'bpm' => 120,
            'director_notes' => 'Watch the pickup.',
        ]);

        $this->actingAs($user)->get(route('studio.charts.show', $song))
            ->assertOk()
            ->assertSee('120 BPM', false)
            ->assertSee('4/4', false)
            ->assertSee('G major', false)
            ->assertSee('Happy', false)
            ->assertSee('Watch the pickup.', false)
            ->assertSee('Song brief', false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('evaluation', false);
    }

    public function test_mood_fallback_when_mood_id_is_null(): void
    {
        $metadata = app(StudioSongMetadata::class)->forSong(
            Song::query()->make(['bpm' => null, 'director_notes' => null]),
        );

        $this->assertSame('Neutral', $metadata['mood_label']);
        $this->assertSame('#5BC0EB', $metadata['mood_colour_hex']);
        $this->assertSame('#8ED4F0', $metadata['mood_accent_colour_hex']);
    }

    public function test_musician_cannot_edit_director_metadata_via_studio_routes(): void
    {
        $user = User::factory()->create();
        $trumpetRef = InstrumentReference::query()->where('slug', 'scaffold-trumpet')->firstOrFail();
        $user->person->instruments()->attach($trumpetRef->id, ['is_primary' => true]);

        $song = $this->seedSongWithMetadata(['name' => 'Locked Metadata Song']);

        $this->actingAs($user)->put(route('studio.charts.show', $song), [
            'director_notes' => 'Musician override attempt',
        ])->assertMethodNotAllowed();

        $this->assertNotSame('Musician override attempt', Song::query()->find($song->id)?->director_notes);
    }

    /**
     * @param  array{name: string, bpm?: int, director_notes?: string}  $overrides
     */
    private function seedSongWithMetadata(array $overrides): Song
    {
        $this->seedReferenceTables();

        $timeSignatureId = TimeSignature::query()->where('label', '4/4')->value('id');
        $musicalKeyId = MusicalKey::query()->where('label', 'G major')->value('id');
        $moodId = SongMood::query()->where('slug', 'happy')->value('id');

        $song = Song::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'song_code' => '050',
            'name' => $overrides['name'],
            'bpm' => $overrides['bpm'] ?? 120,
            'time_signature_id' => $timeSignatureId,
            'musical_key_id' => $musicalKeyId,
            'mood_id' => $moodId,
            'director_notes' => $overrides['director_notes'] ?? null,
            'status' => 'ready',
        ]);

        $part = InstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => 1,
            'name' => 'Trumpet',
            'active' => true,
        ]);

        $chart = Chart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'title' => 'Trumpet Chart',
            'original_filename' => 'trumpet.pdf',
            'storage_reference' => 'charts/1/050/trumpet.pdf',
            'checksum' => hash('sha256', 'charts/1/050/trumpet.pdf'),
            'mime_type' => 'application/pdf',
            'file_size' => 128,
        ]);

        SongInstrumentPart::query()->create([
            'public_id' => (string) Str::uuid(),
            'song_id' => $song->id,
            'instrument_part_id' => $part->id,
            'chart_id' => $chart->id,
        ]);

        return $song->fresh(['timeSignature', 'musicalKey', 'mood']);
    }

    private function seedReferenceTables(): void
    {
        if (SongMood::query()->exists()) {
            return;
        }

        SongMood::query()->create([
            'name' => 'Neutral / Default',
            'slug' => 'neutral',
            'colour_hex' => '#5BC0EB',
            'accent_colour_hex' => '#8ED4F0',
            'sort_order' => 10,
            'active' => true,
        ]);

        SongMood::query()->create([
            'name' => 'Happy',
            'slug' => 'happy',
            'colour_hex' => '#FFB23E',
            'accent_colour_hex' => '#FFD27A',
            'sort_order' => 20,
            'active' => true,
        ]);

        TimeSignature::query()->create(['label' => '4/4', 'sort_order' => 10, 'active' => true]);
        MusicalKey::query()->create(['label' => 'G major', 'tonic' => 'G', 'mode' => 'major', 'sort_order' => 10, 'active' => true]);
    }
}
