<?php

namespace App\Services\Recovery;

class RecoveryCascadeGuardService
{
  /** @var list<string> */
  private const BAND_DEPENDENT_DOMAINS = [
    'instrument_parts',
    'import_audit',
    'songs',
    'cues',
    'charts',
    'song_instrument_parts',
    'snippets',
    'shows',
    'performances',
    'devices',
    'venues',
    'effects',
    'console_baselines',
  ];

  private bool $bandsBlocked = false;

  /** @var list<array<string, mixed>> */
  private array $blocks = [];

  public function recordBandImportResult(int $expected, int $inserted, int $skipped, array $errors): void
  {
    if ($expected > 0 && ($inserted < $expected || ! empty($errors))) {
      $this->bandsBlocked = true;
      $this->blocks[] = [
        'trigger' => 'bands_import_incomplete',
        'expected' => $expected,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'error_count' => count($errors),
      ];
    }
  }

  public function isBlocked(string $domainKey): bool
  {
    if (! $this->bandsBlocked) {
      return false;
    }

    return in_array($domainKey, self::BAND_DEPENDENT_DOMAINS, true);
  }

  /** @return array<string, mixed> */
  public function buildReport(string $batchId): array
  {
    $blockedDomains = $this->bandsBlocked ? self::BAND_DEPENDENT_DOMAINS : [];

    return [
      'version' => 1,
      'schema' => 'esb.recovery.dependency_block_report/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'bands_blocked' => $this->bandsBlocked,
      'blocked_domains' => $blockedDomains,
      'blocks' => $this->blocks,
    ];
  }

  public function bandsBlocked(): bool
  {
    return $this->bandsBlocked;
  }
}
