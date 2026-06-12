<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Band;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VenueController extends Controller
{
    use ResolvesBand;

    public function index(): View
    {
        $band = $this->band();

        return view('venues.index', [
            'band' => $band,
            'activeVenues' => $band->venues()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'archivedVenues' => $band->venues()
                ->where('active', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('venues.create', [
            'band' => $this->band(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();
        $validated = $this->validateVenue($request);

        $this->ensureNameIsUnique($band, $validated['name']);

        $venue = Venue::create([
            'band_id' => $band->id,
            ...$validated,
            'active' => true,
        ]);

        return redirect()
            ->route('venues.index')
            ->with('status', "Venue \"{$venue->name}\" created.");
    }

    public function edit(Venue $venue): View
    {
        $this->ensureBandOwns($venue);

        return view('venues.edit', [
            'band' => $this->band(),
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $this->ensureBandOwns($venue);

        $band = $this->band();
        $validated = $this->validateVenue($request);

        $this->ensureNameIsUnique($band, $validated['name'], ignoreVenueId: $venue->id);

        $venue->update($validated);

        return redirect()
            ->route('venues.index')
            ->with('status', "Venue \"{$venue->name}\" updated.");
    }

    public function archive(Venue $venue): RedirectResponse
    {
        $this->ensureBandOwns($venue);

        $venue->update(['active' => false]);

        return redirect()
            ->route('venues.index')
            ->with('status', "Venue \"{$venue->name}\" archived.");
    }

    public function restore(Venue $venue): RedirectResponse
    {
        $this->ensureBandOwns($venue);

        $venue->update(['active' => true]);

        return redirect()
            ->route('venues.index')
            ->with('status', "Venue \"{$venue->name}\" restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVenue(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'facebook_tag' => ['nullable', 'string', 'max:255'],
            'instagram_tag' => ['nullable', 'string', 'max:255'],
            'tiktok_tag' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function ensureNameIsUnique(Band $band, string $name, ?int $ignoreVenueId = null): void
    {
        $normalized = Venue::normalizeName($name);

        $exists = $band->venues()
            ->when($ignoreVenueId !== null, fn ($query) => $query->where('id', '!=', $ignoreVenueId))
            ->get(['id', 'name'])
            ->contains(fn (Venue $venue) => Venue::normalizeName($venue->name) === $normalized);

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A venue with this name already exists for this band.',
            ]);
        }
    }
}
