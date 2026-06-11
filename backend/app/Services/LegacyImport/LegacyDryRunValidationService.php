<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyDryRunValidationReport;
use App\DataTransferObjects\LegacyImport\LegacyImportConfig;

class LegacyDryRunValidationService
{
    public function __construct(
        private readonly LegacyMigrationPlanService $planService,
        private readonly LegacySetlistLoader $setlistLoader,
        private readonly LegacyDryRunReportBuilder $reportBuilder,
    ) {}

    /**
     * Build a dry-run validation report from legacy sources. Does not write canonical entities or copy assets.
     */
    public function validate(LegacyImportConfig $config): LegacyDryRunValidationReport
    {
        $setlistData = $this->setlistLoader->load($config);
        $setlistCount = count($setlistData['setlists'] ?? []);

        $plan = $this->planService->buildPlan($config);

        return $this->reportBuilder->build($plan, $config, $setlistCount);
    }
}
