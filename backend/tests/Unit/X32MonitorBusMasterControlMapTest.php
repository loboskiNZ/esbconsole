<?php

namespace Tests\Unit;

use App\Services\X32\X32MonitorBusMasterControlMap;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorBusMasterControlMapTest extends TestCase
{
    #[Test]
    public function it_maps_bus_master_fader_and_on_paths_for_selected_bus(): void
    {
        $this->assertSame(
            X32OscAddressMap::busFader(3),
            X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_LEVEL),
        );
        $this->assertSame(
            '/bus/03/mix/fader',
            X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_LEVEL),
        );
        $this->assertSame(
            '/bus/03/mix/on',
            X32MonitorBusMasterControlMap::oscPath(3, X32MonitorBusMasterControlMap::PARAMETER_MUTE),
        );
    }

    #[Test]
    public function mute_ui_inverts_bus_on_semantics(): void
    {
        $this->assertSame(0, X32MonitorBusMasterControlMap::muteToBusOn(true));
        $this->assertSame(1, X32MonitorBusMasterControlMap::muteToBusOn(false));
        $this->assertTrue(X32MonitorBusMasterControlMap::busOnToMuted(0));
        $this->assertFalse(X32MonitorBusMasterControlMap::busOnToMuted(1));
    }

    #[Test]
    public function it_does_not_map_channel_send_eq_or_matrix_paths(): void
    {
        $this->assertNull(X32MonitorBusMasterControlMap::oscPath(1, 'pan'));
        $this->assertNull(X32MonitorBusMasterControlMap::oscPath(1, 'type'));
        $this->assertNotContains('pan', X32MonitorBusMasterControlMap::allowedParameters());
    }
}
