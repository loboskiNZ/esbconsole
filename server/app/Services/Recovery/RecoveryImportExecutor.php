<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RecoveryImportExecutor
{
  /** @var array<string, array<int, int>> */
  private array $idMap = [];

  private bool $musiciansImported = false;

  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryDeferredForeignKeyService $deferredFk,
    private readonly RecoveryEffectTransformService $effectTransform,
    private readonly RecoverySchemaColumnFilter $columnFilter,
    private readonly RecoveryCascadeGuardService $cascadeGuard,
  ) {}

  /** @return array<string, mixed> */
  public function execute(
    string $batchId,
    string $sourceConnection,
    string $targetConnection,
  ): array {
    if (! config('recovery.rehearsal_mode')) {
      throw new RuntimeException('Import execution requires RECOVERY_REHEARSAL_MODE=true for local rehearsal.');
    }

    $this->effectTransform->warmMaps($sourceConnection);
    $domains = [];
    $deferredFkReport = null;
    $bandsExpected = $this->countBundleRows($batchId, 'bands');

    foreach ($this->registry->all() as $domain) {
      if ($this->cascadeGuard->isBlocked($domain['key'])) {
        $domains[] = [
          'domain' => $domain['key'],
          'inserted' => 0,
          'skipped' => 0,
          'errors' => ['dependency_block:bands_import_incomplete'],
          'blocked' => true,
        ];
        continue;
      }

      $result = $this->importDomain(
        $batchId,
        $domain,
        $sourceConnection,
        $targetConnection,
      );

      if ($domain['key'] === 'bands') {
        $this->cascadeGuard->recordBandImportResult(
          $bandsExpected,
          $result['inserted'],
          $result['skipped'],
          $result['errors'],
        );
        $this->storage->writeJson(
          $batchId,
          'dependency_block_report.json',
          $this->cascadeGuard->buildReport($batchId),
        );
      }

      if ($domain['key'] === 'musicians') {
        $this->musiciansImported = $result['inserted'] > 0 || $result['skipped'] > 0;
        if ($this->musiciansImported) {
          $deferredFkReport = $this->deferredFk->replay($batchId, $targetConnection, $this->idMap);
        }
      }

      $domains[] = $result;
    }

    $this->effectTransform->buildReport($batchId);

    return [
      'version' => 1,
      'schema' => 'esb.recovery.import_manifest/v1',
      'batch_id' => $batchId,
      'imported_at' => now()->toIso8601String(),
      'dry_run' => false,
      'rehearsal_mode' => true,
      'deferred_fk_replay' => $deferredFkReport,
      'domains' => $domains,
    ];
  }

  /** @param  array{key: string, tables: list<string>, depends: list<string>, export: bool}  $domain */
  private function importDomain(
    string $batchId,
    array $domain,
    string $sourceConnection,
    string $targetConnection,
  ): array {
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    $rowsByTable = $this->loadDomainRows($batchId, $domain, $sourceConnection);

    foreach ($domain['tables'] as $table) {
      if (! Schema::connection($targetConnection)->hasTable($table)) {
        $errors[] = "target_table_missing:{$table}";
        continue;
      }

      foreach ($rowsByTable[$table] ?? [] as $row) {
        try {
          if ($domain['key'] === 'bands') {
            $row = $this->deferredFk->deferBandDirectorColumn($row);
          }

          if ($domain['key'] === 'effects') {
            $row = $this->effectTransform->transformRow($table, $row);
          }

          $row = $this->columnFilter->filter($targetConnection, $table, $row);

          if ($this->insertRow($batchId, $table, $row, $targetConnection)) {
            $inserted++;
          } else {
            $skipped++;
          }
        } catch (Throwable $e) {
          $skipped++;
          $errors[] = "{$table}:{$row['id']}:{$e->getMessage()}";
        }
      }

      $this->syncSequence($targetConnection, $table);
    }

    return [
      'domain' => $domain['key'],
      'inserted' => $inserted,
      'skipped' => $skipped,
      'errors' => array_slice($errors, 0, 50),
      'blocked' => false,
    ];
  }

  private function countBundleRows(string $batchId, string $domainKey): int
  {
    $path = $this->storage->domainBundlePath($batchId, $domainKey);
    if (! file_exists($path)) {
      return 0;
    }

    return count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
  }

  /**
   * @param  array{key: string, tables: list<string>, depends: list<string>, export: bool}  $domain
   * @return array<string, list<array<string, mixed>>>
   */
  private function loadDomainRows(string $batchId, array $domain, string $sourceConnection): array
  {
    $bundlePath = $this->storage->domainBundlePath($batchId, $domain['key']);
    $rowsByTable = [];

    if (file_exists($bundlePath)) {
      foreach (file($bundlePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $row = json_decode($line, true);
        if (! is_array($row)) {
          continue;
        }

        $table = (string) ($row['_recovery_table'] ?? $domain['tables'][0]);
        unset($row['_recovery_table']);
        $rowsByTable[$table][] = $row;
      }

      return $rowsByTable;
    }

    if (! config('recovery.rehearsal_mode')) {
      return $rowsByTable;
    }

    foreach ($domain['tables'] as $table) {
      if (! Schema::connection($sourceConnection)->hasTable($table)) {
        continue;
      }

      $rows = DB::connection($sourceConnection)->table($table)->orderBy('id')->get();
      foreach ($rows as $row) {
        $rowsByTable[$table][] = (array) $row;
      }
    }

    return $rowsByTable;
  }

  /** @param  array<string, mixed>  $row */
  private function insertRow(string $batchId, string $table, array $row, string $targetConnection): bool
  {
    $sourceId = isset($row['id']) ? (int) $row['id'] : null;

    if ($sourceId !== null && $this->isMapped($table, $sourceId)) {
      return false;
    }

    $payload = $this->normalizeRow($row);

    if ($sourceId !== null && Schema::connection($targetConnection)->hasColumn($table, 'id')) {
      $exists = DB::connection($targetConnection)->table($table)->where('id', $sourceId)->exists();
      if ($exists) {
        $this->recordMap($batchId, $table, $sourceId, $sourceId, $payload['public_id'] ?? null, $targetConnection);

        return false;
      }
    }

    DB::connection($targetConnection)->table($table)->insert($payload);
    $cloudId = $sourceId ?? (int) DB::connection($targetConnection)->getPdo()->lastInsertId();

    if ($sourceId === null && Schema::connection($targetConnection)->hasColumn($table, 'id')) {
      $cloudId = (int) DB::connection($targetConnection)->table($table)->max('id');
    }

    if ($sourceId !== null) {
      $this->recordMap($batchId, $table, $sourceId, $cloudId, $payload['public_id'] ?? null, $targetConnection);
    }

    return true;
  }

  /** @param  array<string, mixed>  $row */
  private function normalizeRow(array $row): array
  {
    foreach ($row as $key => $value) {
      if ($value instanceof \DateTimeInterface) {
        $row[$key] = $value->format('Y-m-d H:i:s');
      }
    }

    return $row;
  }

  private function isMapped(string $table, int $sourceId): bool
  {
    return isset($this->idMap[$table][$sourceId]);
  }

  private function recordMap(string $batchId, string $table, int $sourceId, int $cloudId, mixed $publicId, string $targetConnection): void
  {
    $this->idMap[$table][$sourceId] = $cloudId;

    if (! Schema::connection($targetConnection)->hasTable('cloud_recovery_entity_map')) {
      return;
    }

    DB::connection($targetConnection)->table('cloud_recovery_entity_map')->updateOrInsert(
      [
        'source_env' => config('recovery.source_env', 'live_stage'),
        'table_name' => $table,
        'source_id' => $sourceId,
        'batch_id' => $batchId,
      ],
      [
        'cloud_id' => $cloudId,
        'public_id' => $publicId !== null ? (string) $publicId : null,
        'migrated_at' => now(),
        'updated_at' => now(),
        'created_at' => now(),
      ],
    );
  }

  private function syncSequence(string $connection, string $table): void
  {
    if (DB::connection($connection)->getDriverName() !== 'pgsql') {
      return;
    }

    if (! Schema::connection($connection)->hasColumn($table, 'id')) {
      return;
    }

    DB::connection($connection)->statement(
      "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1))",
    );
  }
}
