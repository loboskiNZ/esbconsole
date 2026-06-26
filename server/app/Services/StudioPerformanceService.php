<?php

namespace App\Services;

use App\Models\Performance;
use App\Models\PerformanceAssignment;
use App\Models\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudioPerformanceService
{
    public function __construct(
        private readonly StudioShowService $shows,
    ) {}

    /**
     * @return Collection<int, Performance>
     */
    public function performancesForPortal(?int $bandId = null): Collection
    {
        $bandId ??= (int) config('portal.band_id', 1);

        return Performance::query()
            ->with('show')
            ->where('band_id', $bandId)
            ->orderByDesc('performance_date')
            ->orderBy('performance_time')
            ->orderBy('location_name')
            ->get();
    }

    /**
     * @return Collection<int, Performance>
     */
    public function performancesForShow(int $showId, ?int $bandId = null): Collection
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $this->shows->showForPortal($showId, $bandId);

        return Performance::query()
            ->where('band_id', $bandId)
            ->where('show_id', $showId)
            ->orderByDesc('performance_date')
            ->orderBy('performance_time')
            ->get();
    }

    /**
     * @return Collection<int, PerformanceAssignment>
     */
    public function availabilityAssignmentsForPerformance(int $performanceId, ?int $bandId = null): Collection
    {
        $performance = $this->performanceForPortal($performanceId, $bandId);

        return PerformanceAssignment::query()
            ->with('musician')
            ->where('performance_id', $performance->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{
     *     show_id: int,
     *     performance_type: string,
     *     status: string,
     *     location_name: string,
     *     location_address?: string|null,
     *     performance_date: string,
     *     prep_time?: string|null,
     *     performance_time?: string|null,
     *     performance_duration_minutes?: int|null,
     *     packup_time?: string|null,
     *     briefing_notes?: string|null,
     * }  $payload
     */
    public function createPerformance(array $payload, ?int $bandId = null): Performance
    {
        $bandId ??= (int) config('portal.band_id', 1);
        $show = $this->shows->showForPortal((int) $payload['show_id'], $bandId);
        $locationName = trim($payload['location_name']);

        return Performance::query()->create([
            'public_id' => (string) Str::uuid(),
            'band_id' => $bandId,
            'show_id' => $show->id,
            'performance_type' => $payload['performance_type'],
            'status' => $payload['status'],
            'venue' => $locationName,
            'location_name' => $locationName,
            'location_address' => $this->normalizeNullableString($payload['location_address'] ?? null),
            'performance_date' => $payload['performance_date'],
            'prep_time' => $this->normalizeNullableTime($payload['prep_time'] ?? null),
            'performance_time' => $this->normalizeNullableTime($payload['performance_time'] ?? null),
            'performance_duration_minutes' => $payload['performance_duration_minutes'] ?? null,
            'packup_time' => $this->normalizeNullableTime($payload['packup_time'] ?? null),
            'briefing_notes' => $this->normalizeNullableString($payload['briefing_notes'] ?? null),
        ]);
    }

    /**
     * @param  array{
     *     show_id: int,
     *     performance_type: string,
     *     status: string,
     *     location_name: string,
     *     location_address?: string|null,
     *     performance_date: string,
     *     prep_time?: string|null,
     *     performance_time?: string|null,
     *     performance_duration_minutes?: int|null,
     *     packup_time?: string|null,
     *     briefing_notes?: string|null,
     * }  $payload
     */
    public function updatePerformance(Performance $performance, array $payload, ?int $bandId = null): Performance
    {
        $portalPerformance = $this->performanceForPortal($performance->id, $bandId);
        $show = $this->shows->showForPortal((int) $payload['show_id'], $bandId);
        $locationName = trim($payload['location_name']);

        $portalPerformance->update([
            'show_id' => $show->id,
            'performance_type' => $payload['performance_type'],
            'status' => $payload['status'],
            'venue' => $locationName,
            'location_name' => $locationName,
            'location_address' => $this->normalizeNullableString($payload['location_address'] ?? null),
            'performance_date' => $payload['performance_date'],
            'prep_time' => $this->normalizeNullableTime($payload['prep_time'] ?? null),
            'performance_time' => $this->normalizeNullableTime($payload['performance_time'] ?? null),
            'performance_duration_minutes' => $payload['performance_duration_minutes'] ?? null,
            'packup_time' => $this->normalizeNullableTime($payload['packup_time'] ?? null),
            'briefing_notes' => $this->normalizeNullableString($payload['briefing_notes'] ?? null),
        ]);

        return $portalPerformance->fresh(['show']);
    }

    public function performanceForPortal(int $performanceId, ?int $bandId = null): Performance
    {
        $bandId ??= (int) config('portal.band_id', 1);

        return Performance::query()
            ->with('show')
            ->where('band_id', $bandId)
            ->findOrFail($performanceId);
    }

    /**
     * @return Collection<int, Show>
     */
    public function selectableShowsForPortal(?int $bandId = null): Collection
    {
        return $this->shows->activeShowsForPortal($bandId);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNullableTime(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
