<?php

namespace Tests\Unit;

use App\Support\StudioLibraryAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesLibrarySchema;
use Tests\TestCase;

class StudioLibraryAvailabilityTest extends TestCase
{
    use CreatesLibrarySchema;
    use RefreshDatabase;

    public function test_library_is_unavailable_when_required_tables_are_missing(): void
    {
        $availability = app(StudioLibraryAvailability::class);

        $this->assertFalse($availability->isAvailable());
    }

    public function test_library_is_available_when_required_tables_exist(): void
    {
        $this->createLibrarySchema();

        $availability = app(StudioLibraryAvailability::class);

        $this->assertTrue($availability->isAvailable());
    }

    public function test_library_is_unavailable_when_any_required_table_is_missing(): void
    {
        $this->createLibrarySchema();
        Schema::dropIfExists('charts');

        $availability = new StudioLibraryAvailability;

        $this->assertFalse($availability->isAvailable());
    }
}
