<?php

namespace App\Http\Controllers;

use App\Services\StudioChartAccessService;
use App\Services\StudioChartSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioChartSearchController extends Controller
{
    public function __invoke(
        Request $request,
        StudioChartSearchService $search,
    ): JsonResponse {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();
        $query = (string) $request->query('q', '');

        return response()->json([
            'results' => $search->search($person, $query),
        ]);
    }
}
