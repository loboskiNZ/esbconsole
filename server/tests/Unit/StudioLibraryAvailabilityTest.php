<?php

namespace Tests\Unit;

use App\Support\StudioLibraryAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudioLibraryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_is_unavailable_when_required_tables_are_missing(): void
    {
        Schema::dropIfExists('song_instrument_parts');
        Schema::dropIfExists('charts');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('instrument_parts');

        $availability = new StudioLibraryAvailability;

        $this->assertFalse($availability->isAvailable());
    }

    public function test_library_is_available_when_required_tables_exist(): void
    {
        $availability = app(StudioLibraryAvailability::class);

        $this->assertTrue($availability->isAvailable());
    }

    public function test_library_is_unavailable_when_any_required_table_is_missing(): void
    {
        Schema::dropIfExists('charts');

        $availability = new StudioLibraryAvailability;

        $this->assertFalse($availability->isAvailable());
    }
}
