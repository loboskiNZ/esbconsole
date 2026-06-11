<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'hasSongsInLibrary' => $band->songs()->exists(),
        ]);
    }

    public function store(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'song_ids' => ['required', 'array', 'min:1'],
            'song_ids.*' => ['integer', 'distinct', 'exists:songs,id'],
        ]);

        $band = $this->band();
        $requestedIds = array_map('intval', $validated['song_ids']);

        $songs = Song::query()
            ->where('band_id', $band->id)
            ->whereIn('id', $requestedIds)
            ->get()
            ->keyBy('id');

        foreach ($requestedIds as $songId) {
            abort_unless($songs->has($songId), 422, 'Invalid song for this band.');
        }

        $existingSongIds = $show->playlistItems()->pluck('song_id')->all();
        $orderedIds = collect($requestedIds)
            ->unique()
            ->sortBy(fn (int $id) => $songs[$id]->song_code)
            ->values();

        $added = 0;
        $skipped = 0;

        DB::transaction(function () use ($show, $orderedIds, $songs, &$existingSongIds, &$added, &$skipped) {
            $nextPosition = (int) $show->playlistItems()->max('position');

            foreach ($orderedIds as $songId) {
                if (in_array($songId, $existingSongIds, true)) {
                    $skipped++;

                    continue;
                }

                $nextPosition++;

                ShowPlaylistItem::create([
                    'show_id' => $show->id,
                    'song_id' => $songId,
                    'position' => $nextPosition,
                ]);

                $existingSongIds[] = $songId;
                $added++;
            }
        });

        if ($added === 0) {
            return redirect()
                ->route('playlist.show', $show)
                ->withErrors(['song_ids' => 'No songs were added. Selected songs may already be on this playlist.']);
        }

        $message = $added === 1
            ? '1 song added to playlist.'
            : "{$added} songs added to playlist.";

        if ($skipped > 0) {
            $message .= ' '.$skipped.' duplicate'.($skipped === 1 ? '' : 's').' skipped.';
        }

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', $message);
    }

    public function reorder(Request $request, Show $show): RedirectResponse|JsonResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct'],
        ]);

        $items = $show->playlistItems()->get()->keyBy('id');
        $orderedIds = array_map('intval', $validated['order']);

        foreach ($orderedIds as $itemId) {
            abort_unless($items->has($itemId), 422, 'Invalid playlist item for this show.');
        }

        $expectedIds = $items->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
        $submittedIds = collect($orderedIds)->sort()->values()->all();

        abort_unless(
            $expectedIds === $submittedIds,
            422,
            'Order must include every playlist item for this show exactly once.',
        );

        $this->applyPlaylistOrder($show, $orderedIds);

        $message = 'Playlist order updated.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', $message);
    }

    public function bulkDestroy(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'playlist_item_ids' => ['required', 'array', 'min:1'],
            'playlist_item_ids.*' => ['integer', 'distinct', 'exists:show_playlist_items,id'],
        ]);

        $requestedIds = array_map('intval', $validated['playlist_item_ids']);
        $items = $show->playlistItems()->whereIn('id', $requestedIds)->get()->keyBy('id');

        foreach ($requestedIds as $itemId) {
            abort_unless($items->has($itemId), 422, 'Invalid playlist item for this show.');
        }

        $removed = $items->count();

        DB::transaction(function () use ($items) {
            ShowPlaylistItem::query()->whereIn('id', $items->pluck('id'))->delete();
        });

        $this->renormalizePlaylistPositions($show);

        $message = $removed === 1
            ? '1 song removed from playlist.'
            : "{$removed} songs removed from playlist.";

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', $message);
    }

    public function destroy(Show $show, ShowPlaylistItem $playlistItem): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlistItem->delete();

        $this->renormalizePlaylistPositions($show);

        return redirect()
            ->route('playlist.show', $show)
            ->with('status', '1 song removed from playlist.');
    }

    private function renormalizePlaylistPositions(Show $show): void
    {
        $orderedIds = $show->playlistItems()->orderBy('position')->pluck('id')->all();

        if ($orderedIds === []) {
            return;
        }

        $this->applyPlaylistOrder($show, array_map('intval', $orderedIds));
    }

    /**
     * @param  array<int, int>  $orderedItemIds
     */
    private function applyPlaylistOrder(Show $show, array $orderedItemIds): void
    {
        foreach ($orderedItemIds as $index => $itemId) {
            ShowPlaylistItem::query()
                ->where('id', $itemId)
                ->where('show_id', $show->id)
                ->update(['position' => 1000 + $index + 1]);
        }

        foreach ($orderedItemIds as $index => $itemId) {
            ShowPlaylistItem::query()
                ->where('id', $itemId)
                ->where('show_id', $show->id)
                ->update(['position' => $index + 1]);
        }
    }
}
