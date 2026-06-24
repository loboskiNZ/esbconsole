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
    $this->info('Entity export order: '.implode(' → ', $plan['entity_order']));
    $this->info('File order: '.implode(' → ', $plan['file_order']));
    $this->newLine();
    $this->table(
      ['Domain', 'Tables', 'Depends on', 'Total rows'],
      collect($plan['domains'])->map(fn (array $d) => [
        $d['domain'],
        implode(', ', $d['tables']),
        implode(', ', $d['depends_on']),
        $d['total_rows'],
      ])->all(),
    );

    return self::SUCCESS;
  }
}
