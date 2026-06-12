<?php

namespace App\Http\Controllers;

use App\Enums\FestivalApplicationStatus;
use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Band;
use App\Models\Festival;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FestivalController extends Controller
{
    use ResolvesBand;

    public function index(): View
    {
        $band = $this->band();

        return view('festivals.index', [
            'band' => $band,
            'activeFestivals' => $band->festivals()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'archivedFestivals' => $band->festivals()
                ->where('active', false)
                ->orderBy('name')
                ->get(),
            'applicationStatuses' => FestivalApplicationStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('festivals.create', [
            'band' => $this->band(),
            'applicationStatuses' => FestivalApplicationStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();
        $validated = $this->validateFestival($request);

        $this->ensureNameIsUnique($band, $validated['name']);

        $festival = Festival::create([
            'band_id' => $band->id,
            ...$validated,
            'active' => true,
        ]);

        return redirect()
            ->route('festivals.index')
            ->with('status', "Festival \"{$festival->name}\" created.");
    }

    public function edit(Festival $festival): View
    {
        $this->ensureBandOwns($festival);

        return view('festivals.edit', [
            'band' => $this->band(),
            'festival' => $festival,
            'applicationStatuses' => FestivalApplicationStatus::cases(),
        ]);
    }

    public function update(Request $request, Festival $festival): RedirectResponse
    {
        $this->ensureBandOwns($festival);

        $band = $this->band();
        $validated = $this->validateFestival($request);

        $this->ensureNameIsUnique($band, $validated['name'], ignoreFestivalId: $festival->id);

        $festival->update($validated);

        return redirect()
            ->route('festivals.index')
            ->with('status', "Festival \"{$festival->name}\" updated.");
    }

    public function archive(Festival $festival): RedirectResponse
    {
        $this->ensureBandOwns($festival);

        $festival->update(['active' => false]);

        return redirect()
            ->route('festivals.index')
            ->with('status', "Festival \"{$festival->name}\" archived.");
    }

    public function restore(Festival $festival): RedirectResponse
    {
        $this->ensureBandOwns($festival);

        $festival->update(['active' => true]);

        return redirect()
            ->route('festivals.index')
            ->with('status', "Festival \"{$festival->name}\" restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFestival(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:2048'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'application_url' => ['nullable', 'string', 'max:2048'],
            'application_deadline' => ['nullable', 'date'],
            'festival_date_notes' => ['nullable', 'string'],
            'application_status' => ['required', 'string', Rule::in(FestivalApplicationStatus::values())],
            'facebook_tag' => ['nullable', 'string', 'max:255'],
            'instagram_tag' => ['nullable', 'string', 'max:255'],
            'tiktok_tag' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function ensureNameIsUnique(Band $band, string $name, ?int $ignoreFestivalId = null): void
    {
        $normalized = Festival::normalizeName($name);

        $exists = $band->festivals()
            ->when($ignoreFestivalId !== null, fn ($query) => $query->where('id', '!=', $ignoreFestivalId))
            ->get(['id', 'name'])
            ->contains(fn (Festival $festival) => Festival::normalizeName($festival->name) === $normalized);

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A festival with this name already exists for this band.',
            ]);
        }
    }
}
