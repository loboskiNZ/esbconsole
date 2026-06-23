<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyPortalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_portal_schema_passes_after_migrations(): void
    {
        $this->artisan('esb:verify-portal-schema', ['--require-pgsql' => false])
            ->assertSuccessful();
    }
}
