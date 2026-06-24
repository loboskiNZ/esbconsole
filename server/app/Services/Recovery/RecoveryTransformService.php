<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecoveryTransformService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
  ) {}

  /** @return array<string, mixed> */
  public function transform(string $batchId): array
  {
    $entries = [];
    $duplicates = [];

    foreach ($this->registry->exportable() as $domain) {
      $bundlePath = $this->storage->domainBundlePath($batchId, $domain['key']);

      if (! File::exists($bundlePath)) {
        continue;
      }

      $seenPublicIds = [];

      foreach (File::lines($bundlePath) as $line) {
        $row = json_decode($line, true);
        if (! is_array($row)) {
          continue;
        }

        $table = $this->inferTableName($domain['key'], $row);
        $sourceId = $row['id'] ?? null;
        $publicId = $row['public_id'] ?? null;

        if ($publicId === null || $publicId === '') {
          $publicId = (string) Str::uuid();
        }

        if (isset($seenPublicIds[$publicId])) {
          $duplicates[] = [
            'domain' => $domain['key'],
            'table_name' => $table,
            'public_id' => $publicId,
            'source_id' => $sourceId,
          ];
        } else {
          $seenPublicIds[$publicId] = true;
        }

        $entries[] = [
          'table_name' => $table,
          'source_id' => $sourceId,
          'cloud_id' => null,
          'public_id' => $publicId,
          'bigint_remap_ready' => true,
        ];
      }
    }

    $payload = [
      'version' => 1,
      'schema' => 'esb.recovery.entity_map/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'entries' => $entries,
      'duplicate_public_ids' => $duplicates,
      'public_id_preservation' => true,
    ];

    $this->storage->writeJson($batchId, 'entity_map.json', $payload);

    return $payload;
  }

  /** @param  array<string, mixed>  $row */
  private function inferTableName(string $domainKey, array $row): string
  {
    $domain = $this->registry->find($domainKey);

    if ($domain === null) {
      return $domainKey;
    }

    return $domain['tables'][0] ?? $domainKey;
  }
}
