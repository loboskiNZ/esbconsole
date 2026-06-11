<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaylistController extends Controller
{
    use ResolvesBand;

    public function show(Show $show): View
    {
        $this->ensureShowBelongsToBand($show);

        session(['active_show_id' => $show->id]);

        $band = $this->band();
        $playlistItems = $show->playlistItems()->with('song')->get();
        $playlistSongIds = $playlistItems->pluck('song_id');

        $availableSongs = $band->songs()
            ->whereNotIn('id', $playlistSongIds)
            ->orderBy('song_code')
            ->get();

        return view('playlist.show', [
            'band' => $band,
            'show' => $show,
            'playlistItems' => $playlistItems,
            'availableSongs' => $availableSongs,
        ]);
    }

    public function store(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'song_id' => ['required', 'integer', 'exists:songs,id'],
        ]);

        $song = Song::findOrFail($validated['song_id']);
        $this->ensureSongBelongsToBand($song);

        abort_if(
            $show->playlistItems()->where('song_id', $song->id)->exists(),
            422,
            'Song is already on this playlist.',
        );

        $nextPosition = (int) $show->playlistItems()->max('position') + 1;

        ShowPlaylistItem::create([
            'show_id' => $show->id,
            'song_id' => $song->id,
            'position' => $nextPosition,
        ]);

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', "\"{$song->name}\" added to playlist.");
    }

    public function reorder(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct'],
        ]);

        $items = $show->playlistItems()->get()->keyBy('id');

        foreach ($validated['order'] as $index => $itemId) {
            abort_unless($items->has($itemId), 422, 'Invalid playlist item.');
        }

        foreach ($validated['order'] as $index => $itemId) {
            ShowPlaylistItem::where('id', $itemId)
                ->where('show_id', $show->id)
                ->update(['position' => 1000 + $index + 1]);
        }

        foreach ($validated['order'] as $index => $itemId) {
            ShowPlaylistItem::where('id', $itemId)
                ->where('show_id', $show->id)
                ->update(['position' => $index + 1]);
        }

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', 'Playlist order updated.');
    }

    public function destroy(Show $show, ShowPlaylistItem $playlistItem): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($playlistItem->show_id === $show->id, 404);

        $songName = $playlistItem->song->name;
        $playlistItem->delete();

        $remaining = $show->playlistItems()->orderBy('position')->get();

        foreach ($remaining as $index => $item) {
            $item->update(['position' => 1000 + $index + 1]);
        }

        foreach ($remaining as $index => $item) {
            $item->update(['position' => $index + 1]);
        }

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', "\"{$songName}\" removed from playlist.");
    }
}
