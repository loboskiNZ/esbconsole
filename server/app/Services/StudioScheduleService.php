<?php

namespace App\Services;

use App\Models\Musician;
use App\Models\Performance;
use App\Models\PerformanceAssignment;
use Illuminate\Support\Collection;

class StudioScheduleService
{
  public function __construct(
    private readonly StudioPerformanceRsvpService $rsvp,
  ) {}

  /**
   * @return Collection<int, Performance>
   */
  public function upcomingPerformancesForPortal(?int $bandId = null, ?int $limit = null): Collection
  {
    $bandId ??= (int) config('portal.band_id', 1);
    $today = now()->toDateString();

    $query = Performance::query()
      ->with('show')
      ->where('band_id', $bandId)
      ->whereDate('performance_date', '>=', $today)
      ->orderBy('performance_date')
      ->orderBy('performance_time')
      ->orderBy('location_name');

    if ($limit !== null) {
      $query->limit($limit);
    }

    return $query->get();
  }

  /**
   * @return Collection<int, Performance>
   */
  public function performancesForCalendar(?int $bandId = null): Collection
  {
    $bandId ??= (int) config('portal.band_id', 1);

    return Performance::query()
      ->with('show')
      ->where('band_id', $bandId)
      ->orderBy('performance_date')
      ->orderBy('performance_time')
      ->orderBy('location_name')
      ->get();
  }

  /**
   * @param  Collection<int, Performance>  $performances
   * @return Collection<int, array{
   *     performance: Performance,
   *     assignment: PerformanceAssignment|null,
   *     rsvp_label: string,
   *     card: array<string, mixed>,
   * }>
   */
  public function buildScheduleItems(Collection $performances, ?Musician $musician): Collection
  {
    return $performances->map(function (Performance $performance) use ($musician): array {
      $assignment = $this->rsvp->assignmentForMusician($performance, $musician);

      return [
        'performance' => $performance,
        'assignment' => $assignment,
        'rsvp_label' => $this->rsvp->rsvpLabelForAssignment($assignment),
        'card' => $this->serializePerformanceCard($performance, $assignment),
      ];
    });
  }

  /**
   * @return array<string, mixed>
   */
  public function serializePerformanceCard(Performance $performance, ?PerformanceAssignment $assignment): array
  {
    return [
      'id' => $performance->id,
      'show_name' => $performance->show?->name ?? 'Show',
      'type' => $performance->typeLabel(),
      'status' => $performance->statusLabel(),
      'date' => $performance->formattedPerformanceDate(),
      'date_iso' => $performance->performance_date?->format('Y-m-d'),
      'time' => $performance->formattedTime($performance->performance_time),
      'location' => $performance->locationNameLabel(),
      'rsvp' => $assignment?->availability_status ?? PerformanceAssignment::AVAILABILITY_UNKNOWN,
      'rsvp_label' => $this->rsvp->rsvpLabelForAssignment($assignment),
      'show_url' => route('studio.performances.show', $performance),
      'ics_url' => route('studio.performances.calendar', $performance),
      'rsvp_url' => route('studio.performances.rsvp', $performance),
    ];
  }
}
