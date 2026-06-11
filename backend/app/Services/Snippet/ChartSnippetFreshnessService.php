<?php

namespace App\Services\Snippet;

use App\Models\Chart;
use App\Models\Snippet;
use Illuminate\Support\Facades\DB;

class ChartSnippetFreshnessService
{
    /**
     * Mark chart-derived snippets out-of-date when a chart asset is updated.
     * Does not delete or regenerate snippet binaries.
     */
    public function markAffectedSnippetsOutOfDate(Chart $chart): int
    {
        return DB::transaction(function () use ($chart): int {
            $affected = Snippet::query()
                ->where('is_active', true)
                ->where('source_type', Snippet::SOURCE_CHART_CROP)
                ->where(function ($query) use ($chart): void {
                    $query->where('source_chart_id', $chart->id)
                        ->orWhereHas('songInstrumentPart', fn ($partQuery) => $partQuery->where('chart_id', $chart->id));
                })
                ->where('freshness_state', Snippet::FRESHNESS_CURRENT)
                ->update(['freshness_state' => Snippet::FRESHNESS_OUT_OF_DATE]);

            return $affected;
        });
    }
}
