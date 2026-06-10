<?php

namespace App\Http\Controllers;

use App\Models\Show;
use App\Services\BandContext;
use Illuminate\View\View;

class PlaylistController extends Controller
{
    public function __construct(
        private readonly BandContext $bandContext,
    ) {}

    public function show(Show $show): View
    {
        $band = $this->bandContext->resolve();

        abort_unless($band && $show->band_id === $band->id, 404);

        session(['active_show_id' => $show->id]);

        $playlistItems = $show->playlistItems()->with('song')->get();

        return view('playlist.show', [
            'band' => $band,
            'show' => $show,
            'playlistItems' => $playlistItems,
        ]);
    }
}
