<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class SongAutoCodeAllocationTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_create_song_form_does_not_accept_manual_song_code(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        $this->actingAs($user)
            ->get(route('songs.create'))
            ->assertOk()
            ->assertDontSee('name="song_code"', false);

        $this->actingAs($user)->post(route('songs.store'), [
            'name' => 'Ignored Manual Code',
            'song_code' => '777',
        ])->assertRedirect();

        $song = Song::query()->where('name', 'Ignored Manual Code')->firstOrFail();
        $this->assertSame('001', $song->song_code);
    }

    public function test_store_assigns_next_available_song_code_for_band(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();

        Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Existing']);
        Song::factory()->forBand($band)->create(['song_code' => '003', 'name' => 'Gap']);

        $this->actingAs($user)->post(route('songs.store'), [
            'name' => 'Auto Assigned',
        ])->assertRedirect();

        $song = Song::query()->where('name', 'Auto Assigned')->firstOrFail();
        $this->assertSame('002', $song->song_code);
    }

    public function test_update_does_not_change_song_code(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create([
            'song_code' => '014',
            'name' => 'Original Name',
            'bpm' => 100,
        ]);

        $this->actingAs($user)->put(route('songs.update', $song), [
            'name' => 'Renamed Song',
            'bpm' => 120,
            'status' => Song::STATUS_DRAFT,
        ])->assertRedirect(route('songs.show', $song));

        $song->refresh();
        $this->assertSame('014', $song->song_code);
        $this->assertSame('Renamed Song', $song->name);
        $this->assertSame(120, $song->bpm);
    }

    public function test_song_show_displays_read_only_runtime_identity(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $song = Song::factory()->forBand($band)->create([
            'song_code' => '015',
            'name' => 'Display Test',
        ]);

        $this->actingAs($user)
            ->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('015')
            ->assertSee('015.CCC');
    }
}
