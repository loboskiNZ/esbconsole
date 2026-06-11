<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Show;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class PlaylistBulkRemoveTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_operator_can_remove_multiple_playlist_songs_in_one_submit(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songs = collect(range(1, 5))->map(function (int $n) use ($band) {
            return Song::factory()->forBand($band)->create([
                'song_code' => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => "Song {$n}",
            ]);
        });

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => $songs->pluck('id')->all(),
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();
        $this->assertCount(5, $items);

        $toRemove = [$items[1]->id, $items[3]->id];

        $response = $this->actingAs($user)->delete(route('playlist.bulk-destroy', $show), [
            'playlist_item_ids' => $toRemove,
        ]);

        $response->assertRedirect(route('playlist.show', $show));
        $response->assertSessionHas('status', '2 songs removed from playlist.');

        $this->assertSame(5, Song::query()->where('band_id', $band->id)->count());

        $remaining = $show->fresh()->playlistItems()->orderBy('position')->get();
        $this->assertCount(3, $remaining);
        $this->assertSame([1, 2, 3], $remaining->pluck('position')->all());
        $this->assertSame(
            [$songs[0]->id, $songs[2]->id, $songs[4]->id],
            $remaining->pluck('song_id')->all()
        );

        $availableIds = Song::query()
            ->where('band_id', $band->id)
            ->whereNotIn('id', $remaining->pluck('song_id'))
            ->orderBy('song_code')
            ->pluck('id')
            ->all();

        $this->assertSame([$songs[1]->id, $songs[3]->id], $availableIds);

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('002 — Song 2')
            ->assertSee('004 — Song 4')
            ->assertSee('name="song_ids[]"', false);
    }

    public function test_reorder_and_add_still_work_after_bulk_removal(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Alpha']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Beta']);
        $songC = Song::factory()->forBand($band)->create(['song_code' => '003', 'name' => 'Gamma']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songA->id, $songB->id, $songC->id],
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();

        $this->actingAs($user)->delete(route('playlist.bulk-destroy', $show), [
            'playlist_item_ids' => [$items[1]->id],
        ])->assertRedirect();

        $remaining = $show->fresh()->playlistItems()->orderBy('position')->get();

        $this->actingAs($user)->post(route('playlist.reorder', $show), [
            'order' => [$remaining[1]->id, $remaining[0]->id],
        ])->assertRedirect();

        $this->assertSame(
            [$songC->id, $songA->id],
            $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all()
        );

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songB->id],
        ])->assertRedirect()
            ->assertSessionHas('status', '1 song added to playlist.');

        $this->assertSame(3, $show->fresh()->playlistItems()->count());
    }

    public function test_single_destroy_route_still_removes_one_item(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $song = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Only Song']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$song->id],
        ])->assertRedirect();

        $item = $show->fresh()->playlistItems()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('playlist.destroy', [$show, $item]))
            ->assertRedirect()
            ->assertSessionHas('status', '1 song removed from playlist.');

        $this->assertSame(0, $show->fresh()->playlistItems()->count());
        $this->assertDatabaseHas('songs', ['id' => $song->id]);
    }

    public function test_playlist_page_exposes_bulk_remove_controls(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);
        $song = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Listed Song']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$song->id],
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('Selected:')
            ->assertSee('name="playlist_item_ids[]"', false)
            ->assertSee('playlist-bulk-remove-form', false);
    }
}
