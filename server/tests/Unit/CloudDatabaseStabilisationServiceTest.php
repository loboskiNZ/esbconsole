<?php

namespace Tests\Unit;

use App\Services\CloudDatabaseStabilisationService;
use Tests\TestCase;

class CloudDatabaseStabilisationServiceTest extends TestCase
{
    public function test_ccmm_table_list_includes_stabilisation_targets(): void
    {
        $this->assertContains('person_invitations', CloudDatabaseStabilisationService::CCMM_TABLES);
        $this->assertContains('cloud_recovery_entity_map', CloudDatabaseStabilisationService::CCMM_TABLES);
        $this->assertContains('mix_moves', CloudDatabaseStabilisationService::CCMM_TABLES);
    }
}
