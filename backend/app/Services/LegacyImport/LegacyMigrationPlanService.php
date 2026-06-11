<?php

namespace App\Services\LegacyImport;

use App\DataTransferObjects\LegacyImport\LegacyImportConfig;
use App\DataTransferObjects\LegacyImport\LegacyMigrationPlan;

class LegacyMigrationPlanService
{
    public function __construct(
        private readonly LegacyMigrationPlanBuilder $planBuilder,
    ) {}

    /**
     * Build a normalized in-memory legacy migration plan. Does not write canonical entities or copy assets.
     */
    public function buildPlan(LegacyImportConfig $config): LegacyMigrationPlan
    {
        return $this->planBuilder->build($config);
    }
}
