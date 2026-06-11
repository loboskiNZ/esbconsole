<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\InstrumentPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstrumentPartController extends Controller
{
    use ResolvesBand;

    public function index(): View
    {
        $band = $this->band();
        $instrumentParts = $band->instrumentParts()->orderBy('name')->get();

        return view('instrument-parts.index', [
            'band' => $band,
            'instrumentParts' => $instrumentParts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        InstrumentPart::create([
            'band_id' => $band->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => true,
        ]);

        return redirect()
            ->route('instrument-parts.index')
            ->with('status', "Instrument part \"{$validated['name']}\" created.");
    }
}
