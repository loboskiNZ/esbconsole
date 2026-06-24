<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryPlanService
{
  public function __construct(
    private readonly RecoveryDomainRegistry $registry,
  ) {}

  /** @return array<string, mixed> */
  public function buildPlan(string $sourceConnection): array
  {
    $domains = [];
    $dependencies = [];

    foreach ($this->registry->all() as $domain) {
      $tableCounts = [];
      $totalRows = 0;

      foreach ($domain['tables'] as $table) {
        $count = $this->countTableRows($sourceConnection, $table);
        $tableCounts[$table] = $count;
        $totalRows += $count;
      }

      $domains[] = [
        'domain' => $domain['key'],
        'tables' => $domain['tables'],
        'depends_on' => $domain['depends'],
        'exportable' => $domain['export'],
        'row_counts' => $tableCounts,
        'total_rows' => $totalRows,
      ];

      foreach ($domain['depends'] as $dep) {
        $dependencies[] = [
          'domain' => $domain['key'],
          'depends_on' => $dep,
        ];
      }
    }

    return [
      'version' => 1,
      'schema' => 'esb.recovery.plan/v1',
      'migration_order' => $this->registry->migrationOrder(),
      'entity_order' => array_map(fn (array $d) => $d['key'], $this->registry->exportable()),
      'file_order' => $this->registry->fileDomains(),
      'domains' => $domains,
      'dependencies' => $dependencies,
    ];
  }

  private function countTableRows(string $connection, string $table): int
  {
    if (! Schema::connection($connection)->hasTable($table)) {
      return 0;
    }

    return (int) DB::connection($connection)->table($table)->count();
  }
}
