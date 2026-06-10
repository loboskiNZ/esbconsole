<?php

namespace App\Http\Controllers;

use App\Models\Show;
use App\Services\BandContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly BandContext $bandContext,
    ) {}

    public function index(): View
    {
        $band = $this->bandContext->resolve();

        abort_unless($band, 404, 'No band configured.');

        $shows = $band->shows()->orderBy('name')->get();
        $activeShowId = session('active_show_id');

        return view('shows.index', [
            'band' => $band,
            'shows' => $shows,
            'activeShowId' => $activeShowId,
        ]);
    }

    public function activate(Request $request, Show $show): RedirectResponse
    {
        $band = $this->bandContext->resolve();

        abort_unless($band && $show->band_id === $band->id, 404);

        session(['active_show_id' => $show->id]);

        return redirect()->route('playlist.show', $show);
    }
}
