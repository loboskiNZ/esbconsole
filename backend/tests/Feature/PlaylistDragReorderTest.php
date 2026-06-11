<?php

namespace Tests\Feature;

use App\Models\Band;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class PlaylistDragReorderTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_json_reorder_persists_new_playlist_order(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songs = collect(range(1, 6))->map(function (int $n) use ($band) {
            return Song::factory()->forBand($band)->create([
                'song_code' => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'name' => "Song {$n}",
            ]);
        });

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => $songs->pluck('id')->all(),
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();
        $newOrder = [
            $items[5]->id,
            $items[0]->id,
            $items[1]->id,
            $items[2]->id,
            $items[3]->id,
            $items[4]->id,
        ];

        $response = $this->actingAs($user)->postJson(route('playlist.reorder', $show), [
            'order' => $newOrder,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Playlist order updated.']);

        $this->assertSame(
            [$songs[5]->id, $songs[0]->id, $songs[1]->id, $songs[2]->id, $songs[3]->id, $songs[4]->id],
            $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all()
        );

        $this->assertSame([1, 2, 3, 4, 5, 6], $show->fresh()->playlistItems()->orderBy('position')->pluck('position')->all());
    }

    public function test_reorder_rejects_items_from_another_show(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $showA = Show::factory()->create(['band_id' => $band->id]);
        $showB = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Show A Song']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Show B Song']);

        $itemA = ShowPlaylistItem::factory()->create([
            'show_id' => $showA->id,
            'song_id' => $songA->id,
            'position' => 1,
        ]);

        ShowPlaylistItem::factory()->create([
            'show_id' => $showB->id,
            'song_id' => $songB->id,
            'position' => 1,
        ]);

        $this->actingAs($user)->postJson(route('playlist.reorder', $showA), [
            'order' => [$itemA->id, ShowPlaylistItem::query()->where('show_id', $showB->id)->value('id')],
        ])->assertStatus(422);
    }

    public function test_reorder_requires_complete_playlist_item_set(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002']);

        $itemA = ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $songA->id, 'position' => 1]);
        ShowPlaylistItem::factory()->create(['show_id' => $show->id, 'song_id' => $songB->id, 'position' => 2]);

        $this->actingAs($user)->postJson(route('playlist.reorder', $show), [
            'order' => [$itemA->id],
        ])->assertStatus(422);
    }

    public function test_multi_select_block_move_preserves_internal_order(): void
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
            'song_ids' => $songs->pluck('id')->all(),
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();

        $blockMoveOrder = [
            $items[0]->id,
            $items[3]->id,
            $items[4]->id,
            $items[1]->id,
            $items[2]->id,
        ];

        $this->actingAs($user)->postJson(route('playlist.reorder', $show), [
            'order' => $blockMoveOrder,
        ])->assertOk();

        $this->assertSame(
            [$songs[0]->id, $songs[3]->id, $songs[4]->id, $songs[1]->id, $songs[2]->id],
            $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all()
        );
    }

    public function test_playlist_page_exposes_drag_handle_and_sortable_list(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);
        $song = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Drag Song']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$song->id],
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('playlist.show', $show))
            ->assertOk()
            ->assertSee('playlist-sortable-list', false)
            ->assertSee('playlist-drag-handle', false)
            ->assertSee('Drag the handle to reorder', false)
            ->assertSee('data-playlist-item-id', false);
    }

    public function test_up_down_reorder_fallback_still_works(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->create(['band_id' => $band->id]);

        $songA = Song::factory()->forBand($band)->create(['song_code' => '001', 'name' => 'Alpha']);
        $songB = Song::factory()->forBand($band)->create(['song_code' => '002', 'name' => 'Beta']);

        $this->actingAs($user)->post(route('playlist.store', $show), [
            'song_ids' => [$songA->id, $songB->id],
        ])->assertRedirect();

        $items = $show->fresh()->playlistItems()->orderBy('position')->get();

        $this->actingAs($user)->post(route('playlist.reorder', $show), [
            'order' => [$items[1]->id, $items[0]->id],
        ])->assertRedirect()
            ->assertSessionHas('status', 'Playlist order updated.');

        $this->assertSame(
            [$songB->id, $songA->id],
            $show->fresh()->playlistItems()->orderBy('position')->pluck('song_id')->all()
        );
    }
}
