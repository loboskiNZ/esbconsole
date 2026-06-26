<?php

namespace App\Services;

use App\Exceptions\StudioMusicianNotLinkedException;
use App\Models\Musician;
use App\Models\Performance;
use App\Models\PerformanceAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudioPerformanceRsvpService
{
  public function __construct(
    private readonly StudioMusicianResolverService $musicians,
    private readonly StudioPerformanceService $performances,
  ) {}

  public function assignmentForMusician(Performance $performance, ?Musician $musician): ?PerformanceAssignment
  {
    if ($musician === null) {
      return null;
    }

    return PerformanceAssignment::query()
      ->where('performance_id', $performance->id)
      ->where('musician_id', $musician->id)
      ->first();
  }

  /**
   * @param  array{response: string, notes?: string|null}  $payload
   */
  public function submitRsvp(User $user, Performance $performance, array $payload, ?int $bandId = null): PerformanceAssignment
  {
    $bandId ??= (int) config('portal.band_id', 1);
    $portalPerformance = $this->performances->performanceForPortal($performance->id, $bandId);
    $musician = $this->musicians->musicianForUser($user, $bandId);

    if ($musician === null) {
      throw StudioMusicianNotLinkedException::forUser();
    }

    $status = $this->mapResponseToAvailabilityStatus($payload['response']);
    $notes = $this->normalizeNotes($payload['notes'] ?? null, $payload['response']);

    return DB::transaction(function () use ($portalPerformance, $musician, $status, $notes, $bandId): PerformanceAssignment {
      $assignment = PerformanceAssignment::query()
        ->where('performance_id', $portalPerformance->id)
        ->where('musician_id', $musician->id)
        ->first();

      if ($assignment === null) {
        $assignment = PerformanceAssignment::query()->create([
          'public_id' => (string) Str::uuid(),
          'performance_id' => $portalPerformance->id,
          'musician_id' => $musician->id,
          'instrument_part_id' => $this->rosterInstrumentPartId($bandId),
          'song_id' => null,
          'cue_id' => null,
          'active' => true,
          'availability_status' => $status,
          'availability_notes' => $notes,
          'responded_at' => now(),
        ]);

        return $assignment;
      }

      $assignment->update([
        'availability_status' => $status,
        'availability_notes' => $notes,
        'responded_at' => now(),
      ]);

      return $assignment->fresh();
    });
  }

  public function rsvpLabelForAssignment(?PerformanceAssignment $assignment): string
  {
    return match ($assignment?->availability_status) {
      PerformanceAssignment::AVAILABILITY_AVAILABLE => 'Yes',
      PerformanceAssignment::AVAILABILITY_UNAVAILABLE => 'No',
      PerformanceAssignment::AVAILABILITY_MAYBE => 'Maybe',
      default => 'Not answered',
    };
  }

  private function mapResponseToAvailabilityStatus(string $response): string
  {
    return match ($response) {
      'yes' => PerformanceAssignment::AVAILABILITY_AVAILABLE,
      'no' => PerformanceAssignment::AVAILABILITY_UNAVAILABLE,
      'maybe' => PerformanceAssignment::AVAILABILITY_MAYBE,
      default => PerformanceAssignment::AVAILABILITY_UNKNOWN,
    };
  }

  private function normalizeNotes(?string $notes, string $response): ?string
  {
    if (! is_string($notes)) {
      return null;
    }

    $trimmed = trim($notes);

    if ($trimmed === '') {
      return null;
    }

    return $trimmed;
  }

  private function rosterInstrumentPartId(int $bandId): int
  {
    $existing = DB::table('instrument_parts')
      ->where('band_id', $bandId)
      ->where('name', 'Roster availability')
      ->value('id');

    if ($existing !== null) {
      return (int) $existing;
    }

    return (int) DB::table('instrument_parts')->insertGetId([
      'public_id' => (string) Str::uuid(),
      'band_id' => $bandId,
      'name' => 'Roster availability',
      'description' => 'Placeholder instrument part for schedule RSVP availability records.',
      'active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
}
