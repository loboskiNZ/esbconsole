<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Musician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MusicianController extends Controller
{
    use ResolvesBand;

    public function index(): View
    {
        $band = $this->band();
        $musicians = $band->musicians()->orderBy('display_name')->get();

        return view('musicians.index', [
            'band' => $band,
            'musicians' => $musicians,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : trim("{$validated['first_name']} {$validated['last_name']}");

        Musician::create([
            'band_id' => $band->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => true,
        ]);

        return redirect()
            ->route('musicians.index')
            ->with('status', "Musician \"{$displayName}\" created.");
    }

    public function edit(Musician $musician): View
    {
        $this->ensureBandOwns($musician);

        return view('musicians.edit', [
            'band' => $this->band(),
            'musician' => $musician,
        ]);
    }

    public function update(Request $request, Musician $musician): RedirectResponse
    {
        $this->ensureBandOwns($musician);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : trim("{$validated['first_name']} {$validated['last_name']}");

        $musician->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        return redirect()
            ->route('musicians.index')
            ->with('status', "Musician \"{$displayName}\" updated.");
    }
}
