<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\MusicalKey;
use App\Models\Song;
use App\Models\SongMood;
use App\Models\TimeSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class SongMetadataTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SongMetadataReferenceSeeder::class);
    }

    public function test_reference_tables_exist_after_migration(): void
    {
        $this->assertTrue(\Schema::hasTable('song_moods'));
        $this->assertTrue(\Schema::hasTable('time_signatures'));
        $this->assertTrue(\Schema::hasTable('musical_keys'));
        $this->assertTrue(\Schema::hasColumn('songs', 'director_notes'));
    }

    public function test_mood_seed_data_exists(): void
    {
        $this->assertDatabaseHas('song_moods', ['slug' => 'neutral']);
        $this->assertDatabaseHas('song_moods', ['slug' => 'happy']);
        $this->assertGreaterThanOrEqual(9, SongMood::query()->count());
    }

    public function test_time_signature_seed_data_exists(): void
    {
        $this->assertDatabaseHas('time_signatures', ['label' => '4/4']);
        $this->assertDatabaseHas('time_signatures', ['label' => '7/8']);
    }

    public function test_musical_key_seed_data_exists(): void
    {
        $this->assertDatabaseHas('musical_keys', ['label' => 'C major']);
        $this->assertDatabaseHas('musical_keys', ['label' => 'B minor']);
    }

    public function test_song_can_store_metadata_fields(): void
    {
        $band = Band::factory()->create();
        $timeSignature = TimeSignature::query()->where('label', '4/4')->firstOrFail();
        $musicalKey = MusicalKey::query()->where('label', 'G major')->firstOrFail();
        $mood = SongMood::query()->where('slug', 'happy')->firstOrFail();

        $song = Song::factory()->forBand($band)->create([
            'bpm' => 128,
            'time_signature_id' => $timeSignature->id,
            'musical_key_id' => $musicalKey->id,
            'mood_id' => $mood->id,
            'director_notes' => 'Keep the groove relaxed in the verse.',
        ]);

        $song->refresh()->load(['timeSignature', 'musicalKey', 'mood']);

        $this->assertSame(128, $song->bpm);
        $this->assertSame('4/4', $song->timeSignature?->label);
        $this->assertSame('G major', $song->musicalKey?->label);
        $this->assertSame('Happy', $song->mood?->name);
        $this->assertSame('Keep the groove relaxed in the verse.', $song->director_notes);
    }

    public function test_invalid_bpm_is_rejected(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => $song->name,
            'bpm' => 12,
            'status' => Song::STATUS_DRAFT,
        ])->assertSessionHasErrors('bpm');
    }

    public function test_invalid_foreign_keys_are_rejected(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => $song->name,
            'time_signature_id' => 99999,
            'musical_key_id' => 99999,
            'mood_id' => 99999,
            'status' => Song::STATUS_DRAFT,
        ])->assertSessionHasErrors(['time_signature_id', 'musical_key_id', 'mood_id']);
    }

    public function test_director_can_update_song_metadata(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create(['name' => 'Metadata Song']);

        $timeSignature = TimeSignature::query()->where('label', '6/8')->firstOrFail();
        $musicalKey = MusicalKey::query()->where('label', 'D minor')->firstOrFail();
        $mood = SongMood::query()->where('slug', 'reflective')->firstOrFail();

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => 'Metadata Song',
            'bpm' => 92,
            'time_signature_id' => $timeSignature->id,
            'musical_key_id' => $musicalKey->id,
            'mood_id' => $mood->id,
            'director_notes' => 'Build into chorus with energy.',
            'status' => Song::STATUS_IN_PROGRESS,
        ])->assertRedirect(route('songs.show', $song));

        $song->refresh();
        $this->assertSame(92, $song->bpm);
        $this->assertSame($timeSignature->id, $song->time_signature_id);
        $this->assertSame('Build into chorus with energy.', $song->director_notes);
    }

    public function test_director_show_displays_metadata_without_evaluation_language(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $mood = SongMood::query()->where('slug', 'powerful')->firstOrFail();
        $song = Song::factory()->forBand($band)->create([
            'bpm' => 110,
            'mood_id' => $mood->id,
            'director_notes' => 'Drive the chorus.',
        ]);

        $this->actingAs($user)->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('110', false)
            ->assertSee('Powerful', false)
            ->assertSee('Drive the chorus.', false)
            ->assertDontSee('Readiness score', false)
            ->assertDontSee('completion', false)
            ->assertDontSee('evaluation', false);
    }
}
