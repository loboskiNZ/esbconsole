<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\InstrumentPart;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class BulkSongCreationTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_bulk_create_assigns_codes_instruments_and_skips_duplicates(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        $voice = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Voice']);
        $keys = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Keys']);
        $machines = InstrumentPart::factory()->create(['band_id' => $band->id, 'name' => 'Machines']);

        $response = $this->actingAs($user)->post(route('songs.bulk-store'), [
            'song_names' => "Callejero\nSweet Caroline\n\nCallejero\n",
            'instrument_part_ids' => [$voice->id, $keys->id, $machines->id],
        ]);

        $response->assertRedirect(route('songs.bulk-create'));
        $response->assertSessionHas('bulk_result');

        $result = $response->getSession()->get('bulk_result');
        $this->assertSame(2, $result['created_count']);
        $this->assertSame(1, $result['skipped_count']);

        $this->assertDatabaseCount('songs', 2);
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'name' => 'Callejero']);
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'name' => 'Sweet Caroline']);

        $callejero = Song::query()->where('band_id', $band->id)->where('name', 'Callejero')->firstOrFail();
        $sweetCaroline = Song::query()->where('band_id', $band->id)->where('name', 'Sweet Caroline')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{3}$/', $callejero->song_code);
        $this->assertMatchesRegularExpression('/^\d{3}$/', $sweetCaroline->song_code);
        $this->assertNotSame($callejero->song_code, $sweetCaroline->song_code);

        foreach ([$callejero, $sweetCaroline] as $song) {
            $this->assertSame(3, $song->songInstrumentParts()->count());
            $this->assertSame(
                [$voice->id, $keys->id, $machines->id],
                $song->songInstrumentParts()->pluck('instrument_part_id')->sort()->values()->all()
            );
        }

        $this->assertSame('Callejero', $result['skipped'][0]['name']);
        $this->assertStringContainsString('Duplicate', $result['skipped'][0]['reason']);
    }

    public function test_bulk_create_skips_existing_band_song_names(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Existing Song']);

        $response = $this->actingAs($user)->post(route('songs.bulk-store'), [
            'song_names' => "Existing Song\nBrand New Song\n",
        ]);

        $response->assertRedirect(route('songs.bulk-create'));

        $result = $response->getSession()->get('bulk_result');
        $this->assertSame(1, $result['created_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertSame('Existing Song', $result['skipped'][0]['name']);
        $this->assertStringContainsString('already exists', $result['skipped'][0]['reason']);
    }

    public function test_bulk_create_rejects_manual_song_code_input(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)->post(route('songs.bulk-store'), [
            'song_names' => "Test Song\n",
            'song_code' => '999',
        ])->assertSessionHasErrors('song_code');
    }

    public function test_bulk_create_page_is_accessible_from_songs_index(): void
    {
        $user = $this->createDirectorUser();
        Band::factory()->create();

        $this->actingAs($user)
            ->get(route('songs.index'))
            ->assertOk()
            ->assertSee('Bulk Create');

        $this->actingAs($user)
            ->get(route('songs.bulk-create'))
            ->assertOk()
            ->assertSee('Paste one song title per line');
    }
}
