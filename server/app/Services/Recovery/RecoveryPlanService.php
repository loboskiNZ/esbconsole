<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryPlanService
{
  /** @var list<string> */
  private const DEFERRED_FK_PATH = ['bands', 'musicians', 'bands.deferred_fk_replay'];

  /** @var list<string> */
  private const CRITICAL_PATH = [
    'reference', 'bands', 'musicians', 'songs', 'charts', 'shows', 'console_baselines',
  ];

  /** @var list<string> */
  private const BAND_CASCADE_DOMAINS = [
    'songs', 'charts', 'shows', 'performances', 'console_baselines',
  ];

  public function __construct(
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryDeferredForeignKeyService $deferredFk,
  ) {}

  /** @return array<string, mixed> */
  public function buildPlan(string $sourceConnection, ?string $batchId = null): array
  {
    $domains = [];
    $dependencies = [];
    $dependencyTree = [];

    foreach ($this->registry->all() as $domain) {
      $tableCounts = [];
      $totalRows = 0;

      foreach ($domain['tables'] as $table) {
        $count = $this->countTableRows($sourceConnection, $table);
        $tableCounts[$table] = $count;
        $totalRows += $count;
      }

      $node = [
        'domain' => $domain['key'],
        'tables' => $domain['tables'],
        'depends_on' => $domain['depends'],
        'exportable' => $domain['export'],
        'row_counts' => $tableCounts,
        'total_rows' => $totalRows,
        'blocked_if_bands_fail' => in_array($domain['key'], self::BAND_CASCADE_DOMAINS, true),
      ];

      $domains[] = $node;
      $dependencyTree[$domain['key']] = [
        'depends_on' => $domain['depends'],
        'children' => $this->childDomains($domain['key']),
      ];

      foreach ($domain['depends'] as $dep) {
        $dependencies[] = [
          'domain' => $domain['key'],
          'depends_on' => $dep,
        ];
      }
    }

    $deferredFkPreview = $batchId !== null
      ? count($this->deferredFk->captureFromBandsBundle($batchId))
      : $this->countDeferredFkCandidates($sourceConnection);

    return [
      'version' => 2,
      'schema' => 'esb.recovery.plan/v2',
      'migration_order' => $this->registry->migrationOrder(),
      'entity_order' => array_map(fn (array $d) => $d['key'], $this->registry->exportable()),
      'file_order' => $this->registry->fileDomains(),
      'domains' => $domains,
      'dependencies' => $dependencies,
      'dependency_tree' => $dependencyTree,
      'critical_path' => self::CRITICAL_PATH,
      'deferred_fk_path' => self::DEFERRED_FK_PATH,
      'deferred_fk_candidates' => $deferredFkPreview,
      'blocked_domains_if_bands_fail' => self::BAND_CASCADE_DOMAINS,
      'non_exported_prerequisites' => $this->nonExportedPrerequisites(),
    ];
  }

  /** @return list<string> */
  private function childDomains(string $parentKey): array
  {
    $children = [];
    foreach ($this->registry->all() as $domain) {
      if (in_array($parentKey, $domain['depends'], true)) {
        $children[] = $domain['key'];
      }
    }

    return $children;
  }

  /** @return list<string> */
  private function nonExportedPrerequisites(): array
  {
    return array_values(array_map(
      fn (array $d) => $d['key'],
      array_filter($this->registry->all(), fn (array $d) => ! $d['export']),
    ));
  }

  private function countDeferredFkCandidates(string $sourceConnection): int
  {
    if (! Schema::connection($sourceConnection)->hasTable('bands')) {
      return 0;
    }

    return (int) DB::connection($sourceConnection)
      ->table('bands')
      ->whereNotNull('primary_director_musician_id')
      ->count();
  }

  private function countTableRows(string $connection, string $table): int
  {
    if (! Schema::connection($connection)->hasTable($table)) {
      return 0;
    }

    return (int) DB::connection($connection)->table($table)->count();
  }
}
