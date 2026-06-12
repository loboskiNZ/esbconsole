<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Services\BulkVenueCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkVenueController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly BulkVenueCreationService $bulkVenueCreationService,
    ) {}

    public function create(): View
    {
        return view('venues.bulk-create', [
            'band' => $this->band(),
            'bulkResult' => session('bulk_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'venue_lines' => ['required', 'string'],
        ]);

        $result = $this->bulkVenueCreationService->create(
            $band,
            $validated['venue_lines'],
        );

        if ($result->createdCount() === 0 && $result->skippedCount() === 0) {
            return redirect()
                ->route('venues.bulk-create')
                ->withErrors(['venue_lines' => 'Enter at least one venue with a name.'])
                ->withInput();
        }

        return redirect()
            ->route('venues.bulk-create')
            ->with('bulk_result', [
                'created' => $result->created,
                'skipped' => $result->skipped,
                'created_count' => $result->createdCount(),
                'skipped_count' => $result->skippedCount(),
            ]);
    }
}
