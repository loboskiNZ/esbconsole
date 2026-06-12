<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Services\BulkFestivalCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkFestivalController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly BulkFestivalCreationService $bulkFestivalCreationService,
    ) {}

    public function create(): View
    {
        return view('festivals.bulk-create', [
            'band' => $this->band(),
            'bulkResult' => session('bulk_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'festival_lines' => ['required', 'string'],
        ]);

        $result = $this->bulkFestivalCreationService->create(
            $band,
            $validated['festival_lines'],
        );

        if ($result->createdCount() === 0 && $result->skippedCount() === 0) {
            return redirect()
                ->route('festivals.bulk-create')
                ->withErrors(['festival_lines' => 'Enter at least one festival with a name.'])
                ->withInput();
        }

        return redirect()
            ->route('festivals.bulk-create')
            ->with('bulk_result', [
                'created' => $result->created,
                'skipped' => $result->skipped,
                'created_count' => $result->createdCount(),
                'skipped_count' => $result->skippedCount(),
            ]);
    }
}
