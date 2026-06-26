<?php

namespace App\Services;

use App\Models\Performance;
use Carbon\Carbon;

class PerformanceIcsExportService
{
  public function build(Performance $performance): string
  {
    $performance->loadMissing('show');

    $summary = trim(($performance->show?->name ?? 'Performance').' — '.$performance->typeLabel());
    $location = trim(implode(', ', array_filter([
      $performance->locationNameLabel() !== '—' ? $performance->locationNameLabel() : null,
      $performance->location_address,
    ])));
    $description = $this->escapeIcsText($performance->briefingNotesLabel() ?? '');
    $url = route('studio.performances.show', $performance);
    $uid = 'performance-'.$performance->public_id.'@esb-studio';

    [$dtStart, $dtEnd, $allDay] = $this->resolveEventTimes($performance);

    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//ESB Studio//Performance Schedule//EN',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'BEGIN:VEVENT',
      'UID:'.$uid,
      'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
      'SUMMARY:'.$this->escapeIcsText($summary),
      'URL:'.$this->escapeIcsText($url),
    ];

    if ($allDay) {
      $lines[] = 'DTSTART;VALUE=DATE:'.$dtStart;
      $lines[] = 'DTEND;VALUE=DATE:'.$dtEnd;
    } else {
      $lines[] = 'DTSTART:'.$dtStart;
      $lines[] = 'DTEND:'.$dtEnd;
    }

    if ($location !== '') {
      $lines[] = 'LOCATION:'.$this->escapeIcsText($location);
    }

    if ($description !== '') {
      $lines[] = 'DESCRIPTION:'.$description;
    }

    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines)."\r\n";
  }

  /**
   * @return array{0: string, 1: string, 2: bool}
   */
  private function resolveEventTimes(Performance $performance): array
  {
    $date = $performance->performance_date?->format('Y-m-d');

    if ($date === null) {
      $start = now()->format('Ymd\THis');
      $end = now()->addHour()->format('Ymd\THis');

      return [$start, $end, false];
    }

    if (! filled($performance->performance_time)) {
      $startDate = Carbon::parse($date)->format('Ymd');
      $endDate = Carbon::parse($date)->addDay()->format('Ymd');

      return [$startDate, $endDate, true];
    }

    $start = Carbon::parse($date.' '.$performance->performance_time);
    $duration = max(1, (int) ($performance->performance_duration_minutes ?? 60));
    $end = $start->copy()->addMinutes($duration);

    return [$start->format('Ymd\THis'), $end->format('Ymd\THis'), false];
  }

  private function escapeIcsText(string $value): string
  {
    $escaped = str_replace(
      ["\\", ';', ',', "\r\n", "\n", "\r"],
      ['\\\\', '\;', '\,', '\n', '\n', '\n'],
      $value,
    );

    return $escaped;
  }
}
