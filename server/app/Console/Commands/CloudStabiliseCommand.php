<?php

namespace App\Console\Commands;

use App\Services\CloudDatabaseStabilisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CloudStabiliseCommand extends Command
{
    protected $signature = 'cloud:stabilise
                            {--compare : Compare CCMM tables between source and target connections}
                            {--mark-migrations : Mark existing CCMM migration files as applied on target}
                            {--migrate-additive : Run additive stabilisation migration on target connection}
                            {--sync : Upsert local data into target without overwriting preserved rows}
                            {--dry-run : Report actions without writing}
                            {--source=cloud_stabilise_source : Source database connection}
                            {--target=cloud_stabilise_target : Target database connection}';

    protected $description = 'Compare, mark, migrate, and sync Cloud Database schema/data without destructive operations';

    public function handle(CloudDatabaseStabilisationService $service): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('compare') && ! $this->option('mark-migrations') && ! $this->option('migrate-additive') && ! $this->option('sync')) {
            $this->error('Specify at least one of --compare, --mark-migrations, --migrate-additive, or --sync');

            return self::FAILURE;
        }

        if ($this->option('compare')) {
            $report = $service->compare($source, $target);
            $this->info('Schema comparison');
            $this->line('Source: '.$report['source']);
            $this->line('Target: '.$report['target']);
            $this->line('Missing on target: '.implode(', ', $report['missing_on_target']) ?: '(none)');
            $this->line('Missing on source: '.implode(', ', $report['missing_on_source']) ?: '(none)');
        }

        if ($this->option('mark-migrations')) {
            $result = $service->markExistingCcmmMigrations($target, $dryRun);
            $this->info(($dryRun ? '[dry-run] ' : '').'Marked CCMM migrations: '.$result['inserted'].' (skipped '.$result['skipped'].')');
        }

        if ($this->option('migrate-additive')) {
            if ($dryRun) {
                $this->info('[dry-run] Would run additive stabilisation migration on target connection');
            } else {
                $migrationPath = '../database/migrations/ccmm/2026_06_25_001400_cloud_stabilisation_additive.php';
                $exit = Artisan::call('migrate', [
                    '--database' => $target,
                    '--path' => $migrationPath,
                    '--force' => true,
                ]);
                $this->line(Artisan::output());
                if ($exit !== 0) {
                    return self::FAILURE;
                }
            }
        }

        if ($this->option('sync')) {
            $summary = $service->syncData($source, $target, $dryRun);
            $this->info(($dryRun ? '[dry-run] ' : '').'Data sync summary');
            foreach ($summary as $table => $stats) {
                $this->line(sprintf(
                    '  %-32s inserted=%d skipped=%d preserved=%d',
                    $table,
                    $stats['inserted'],
                    $stats['skipped'],
                    $stats['preserved'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
