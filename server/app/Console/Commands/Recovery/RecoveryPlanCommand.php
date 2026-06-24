<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryPlanService;

class RecoveryPlanCommand extends RecoveryCommand
{
  protected $signature = 'recovery:plan {--json : Output JSON only}';

  protected $description = 'Print recovery migration order and dependency report (read-only)';

  public function handle(RecoveryPlanService $planner): int
  {
    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    $plan = $planner->buildPlan($this->sourceConnection());

    if ($this->option('json')) {
      $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

      return self::SUCCESS;
    }

    $this->info('Migration order: '.implode(' → ', $plan['migration_order']));
    $this->info('Critical path: '.implode(' → ', $plan['critical_path']));
    $this->info('Deferred FK path: '.implode(' → ', $plan['deferred_fk_path']));
    $this->info('Deferred FK candidates: '.($plan['deferred_fk_candidates'] ?? 0));
    $this->info('Blocked if bands fail: '.implode(', ', $plan['blocked_domains_if_bands_fail'] ?? []));
    $this->info('Non-exported prerequisites: '.implode(', ', $plan['non_exported_prerequisites'] ?? []));
    $this->newLine();
    $this->table(
      ['Domain', 'Tables', 'Depends on', 'Total rows', 'Band-fail block'],
      collect($plan['domains'])->map(fn (array $d) => [
        $d['domain'],
        implode(', ', $d['tables']),
        implode(', ', $d['depends_on']),
        $d['total_rows'],
        ($d['blocked_if_bands_fail'] ?? false) ? 'yes' : 'no',
      ])->all(),
    );

    return self::SUCCESS;
  }
}
