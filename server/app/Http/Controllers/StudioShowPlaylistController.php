<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderShowPlaylistRequest;
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
    public function store(
        StoreShowPlaylistItemRequest $request,
        Show $show,
        StudioShowPlaylistService $playlist,
    ): RedirectResponse {
        try {
            $playlist->addSongToPlaylist($show, (int) $request->validated()['song_id']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['song_id' => $exception->getMessage()]);
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
