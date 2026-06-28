<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderShowPlaylistRequest;
use App\Http\Requests\SearchShowPlaylistSongsRequest;
use App\Http\Requests\StoreShowPlaylistItemRequest;
use App\Http\Requests\UpdateShowPlaylistItemNotesRequest;
use App\Models\Show;
use App\Models\ShowPlaylistItem;
use App\Services\StudioShowPlaylistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class StudioShowPlaylistController extends Controller
{
    public function searchSongs(
        SearchShowPlaylistSongsRequest $request,
        Show $show,
        StudioShowPlaylistService $playlist,
    ): JsonResponse {
        return response()->json([
            'results' => $playlist->searchSongsForPlaylist(
                $show->id,
                (string) $request->validated()['q'],
            ),
        ]);
    }

    public function store(
        StoreShowPlaylistItemRequest $request,
        Show $show,
        StudioShowPlaylistService $playlist,
    ): JsonResponse|RedirectResponse {
        try {
            $item = $playlist->addSongToPlaylist($show, (int) $request->validated()['song_id']);
        } catch (InvalidArgumentException $exception) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['song_id' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            $view = $playlist->playlistViewForShow($show->id, viewer: $request->user());
            $entry = $view['entries']->first(
                fn (array $entry): bool => (int) $entry['item']->id === (int) $item->id,
            );

            abort_if($entry === null, 500, 'Playlist item could not be rendered.');

            return response()->json([
                'ok' => true,
                'message' => 'Song added to playlist.',
                'summary' => $playlist->summaryForResponse($view['summary']),
                'html' => view('studio.shows.partials._playlist-item', [
                    'entry' => $entry,
                    'show' => $show,
                    'isDirector' => true,
                ])->render(),
            ]);
        }

        return back()->with('playlist_updated', true);
    }

    public function reorder(
        ReorderShowPlaylistRequest $request,
        Show $show,
        StudioShowPlaylistService $playlist,
    ): JsonResponse {
        try {
            $positions = $playlist->reorderPlaylistItems($show, $request->validated()['order']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'positions' => $positions,
        ]);
    }

    public function destroy(
        Show $show,
        ShowPlaylistItem $playlistItem,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlist->archivePlaylistItem($playlistItem);

        return back()->with('playlist_updated', true);
    }

    public function updateNotes(
        UpdateShowPlaylistItemNotesRequest $request,
        Show $show,
        ShowPlaylistItem $playlistItem,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlist->updatePlaylistItemNotes($playlistItem, $request->validated()['notes'] ?? null);

        return back()->with('playlist_updated', true);
    }

    public function archive(
        Show $show,
        ShowPlaylistItem $playlistItem,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlist->archivePlaylistItem($playlistItem);

        return back()->with('playlist_updated', true);
    }

    public function moveUp(
        Show $show,
        ShowPlaylistItem $playlistItem,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlist->movePlaylistItemUp($playlistItem);

        return back()->with('playlist_updated', true);
    }

    public function moveDown(
        Show $show,
        ShowPlaylistItem $playlistItem,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        abort_unless($playlistItem->show_id === $show->id, 404);

        $playlist->movePlaylistItemDown($playlistItem);

        return back()->with('playlist_updated', true);
    }
}
