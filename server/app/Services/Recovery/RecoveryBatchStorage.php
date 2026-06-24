<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RecoveryBatchStorage
{
  public function resolveBatchId(?string $batchId): string
  {
    if ($batchId !== null && $batchId !== '') {
      return $batchId;
    }

    return (string) Str::uuid();
  }

  public function batchPath(string $batchId): string
  {
    return storage_path('recovery/'.$batchId);
  }

  public function ensureBatchDirectory(string $batchId): string
  {
    $path = $this->batchPath($batchId);
    File::ensureDirectoryExists($path);
    File::ensureDirectoryExists($path.'/domains');

    return $path;
  }

  /** @param  array<string, mixed>  $payload */
  public function writeJson(string $batchId, string $filename, array $payload): string
  {
    $path = $this->ensureBatchDirectory($batchId).'/'.$filename;
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($encoded === false) {
      throw new RuntimeException("Failed to encode JSON for {$filename}.");
    }

    File::put($path, $encoded);

    return $path;
  }

  /** @return array<string, mixed> */
  public function readJson(string $batchId, string $filename): array
  {
    $path = $this->batchPath($batchId).'/'.$filename;

    if (! File::exists($path)) {
      throw new RuntimeException("Recovery artifact missing: {$filename}");
    }

    $decoded = json_decode(File::get($path), true);

    if (! is_array($decoded)) {
      throw new RuntimeException("Recovery artifact invalid: {$filename}");
    }

    return $decoded;
  }

  public function domainsPath(string $batchId): string
  {
    return $this->ensureBatchDirectory($batchId).'/domains';
  }

  public function domainBundlePath(string $batchId, string $domain): string
  {
    return $this->domainsPath($batchId).'/'.$domain.'.jsonl';
  }
}
