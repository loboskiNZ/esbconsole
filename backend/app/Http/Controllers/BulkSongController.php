<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Services\BulkSongCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkSongController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly BulkSongCreationService $bulkSongCreationService,
    ) {}

    public function create(): View
    {
        $band = $this->band();

        return view('songs.bulk-create', [
            'band' => $band,
            'instrumentParts' => $band->instrumentParts()->where('active', true)->orderBy('name')->get(),
            'bulkResult' => session('bulk_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'song_names' => ['required', 'string'],
            'instrument_part_ids' => ['nullable', 'array'],
            'instrument_part_ids.*' => ['integer', 'distinct', 'exists:instrument_parts,id'],
            'song_code' => ['prohibited'],
        ]);

        $instrumentPartIds = array_map('intval', $validated['instrument_part_ids'] ?? []);

        foreach ($instrumentPartIds as $instrumentPartId) {
            abort_unless(
                $band->instrumentParts()->whereKey($instrumentPartId)->exists(),
                422,
                'Invalid instrument part for this band.',
            );
        }

        $result = $this->bulkSongCreationService->create(
            $band,
            $validated['song_names'],
            $instrumentPartIds,
        );

        if ($result->createdCount() === 0 && $result->skippedCount() === 0) {
            return redirect()
                ->route('songs.bulk-create')
                ->withErrors(['song_names' => 'Enter at least one song name.'])
                ->withInput();
        }

        return redirect()
            ->route('songs.bulk-create')
            ->with('bulk_result', [
                'created' => $result->created,
                'skipped' => $result->skipped,
                'created_count' => $result->createdCount(),
                'skipped_count' => $result->skippedCount(),
            ]);
    }
}
