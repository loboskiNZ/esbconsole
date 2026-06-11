<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class PlaylistMultiAddTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_operator_can_add_multiple_songs_in_one_submit(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songs = collect(range(1, 10))->map(function (int $n) use ($band) {
            return Song::factory()->forBand($band)->create([
                'song_code' => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => "Song {$n}",
            ]);
        });

        $firstBatch = [$songs[0]->id, $songs[1]->id, $songs[2]->id];

        $response = $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => $firstBatch,
        ]);

        $response->assertRedirect(route('playlist.show', $show));
        $response->assertSessionHas('status', '3 songs added to playlist.');

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();
        $this->assertCount(3, $items);
        $this->assertSame($firstBatch, $items->pluck('song_id')->all());
        $this->assertSame([1, 2, 3], $items->pluck('position')->all());

        $this->assertSame(
            [4, 5, 6, 7, 8, 9, 10],
            Song::query()
                ->where('band_id', $band->id)
                ->whereNotIn('id', $show->fresh()->playlistItems()->pluck('song_id'))
                ->orderBy('song_code')
                ->pluck('id')
                ->all()
        );

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('004 — Song 4')
            ->assertSee('010 — Song 10')
            ->assertSee('Select All');
    }

    public function test_select_all_adds_remaining_songs_and_hides_them_from_available_list(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songs = collect(range(1, 5))->map(function (int $n) use ($band) {
            return Song::factory()->forBand($band)->create([
                'song_code' => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => "Track {$n}",
            ]);
        });

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songs[0]->id],
        ])->assertRedirect();

        $remainingIds = $songs->slice(1)->pluck('id')->values()->all();

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => $remainingIds,
        ])->assertRedirect()
            ->assertSessionHas('status', '4 songs added to playlist.');

        $this->assertSame(5, $show->fresh()->playlistItems()->count());

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('All songs have already been added to this playlist.')
            ->assertDontSee('name="song_ids[]"', false);
    }

    public function test_duplicate_playlist_entries_are_skipped(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Alpha']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Beta']);

        ShowPlaylistItem::factory()->create([
            'show_id' => $show->id,
            'song_id' => $songA->id,
            'position' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songA->id, $songB->id],
        ]);

        $response->assertRedirect(route('playlist.show', $show));
        $response->assertSessionHas('status', '1 song added to playlist. 1 duplicate skipped.');

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame([$songA->id, $songB->id], $items->pluck('song_id')->all());
    }

    public function test_reorder_and_remove_still_work_after_multi_add(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'First']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Second']);
        $songC = Song::factory()->forBand($band)->create(['song_code' => '003', 'name' => 'Third']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songA->id, $songB->id, $songC->id],
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();

        $this->actingAs($user)->post(route('playlist.reorder', $show), [
            'order' => [$items[2]->id, $items[0]->id, $items[1]->id],
        ])->assertRedirect();

        $reordered = $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all();
        $this->assertSame([$songC->id, $songA->id, $songB->id], $reordered);

        $this->actingAs($user)->delete(route('playlist.destroy', [$show, $items[1]->fresh()]))
            ->assertRedirect();

        $this->assertSame(2, $show->fresh()->playlistItems()->count());
    }

    public function test_playlist_page_exposes_multi_select_controls(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);
        Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Available Song']);

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('Select All')
            ->assertSee('Clear Selection')
            ->assertSee('Selected:')
            ->assertSee('name="song_ids[]"', false);
    }
}
