<?php

namespace Tests\Unit;

use App\Services\X32\X32BusEqOscDecoder;
use App\Services\X32\X32MonitorBusEqControlMap;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorBusEqControlMapTest extends TestCase
{
    #[Test]
    public function it_maps_bus_eq_master_on_path_for_selected_bus(): void
    {
        $this->assertSame(
            '/bus/03/eq/on',
            X32MonitorBusEqControlMap::oscPath(3, X32MonitorBusEqControlMap::PARAMETER_ON),
        );
    }

    #[Test]
    public function it_maps_band_parameter_paths_for_selected_bus(): void
    {
        $this->assertSame(
            X32OscAddressMap::busEqBandType(4, 2),
            X32MonitorBusEqControlMap::oscPath(4, X32MonitorBusEqControlMap::PARAMETER_TYPE, 2),
        );
        $this->assertSame(
            X32OscAddressMap::busEqBandFrequency(4, 2),
            X32MonitorBusEqControlMap::oscPath(4, X32MonitorBusEqControlMap::PARAMETER_FREQUENCY, 2),
        );
        $this->assertSame(
            X32OscAddressMap::busEqBandGain(4, 2),
            X32MonitorBusEqControlMap::oscPath(4, X32MonitorBusEqControlMap::PARAMETER_GAIN, 2),
        );
        $this->assertSame(
            X32OscAddressMap::busEqBandQ(4, 2),
            X32MonitorBusEqControlMap::oscPath(4, X32MonitorBusEqControlMap::PARAMETER_Q, 2),
        );
    }

    #[Test]
    public function it_does_not_map_channel_or_send_eq_paths(): void
    {
        $this->assertNull(X32MonitorBusEqControlMap::oscPath(1, 'level', 1));
        $this->assertNull(X32MonitorBusEqControlMap::oscPath(1, 'pan', 1));
        $this->assertNull(X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_TYPE));
        $this->assertStringStartsWith('/bus/', X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_GAIN, 1));
        $this->assertStringNotContainsString('/ch/', X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_GAIN, 1));
    }

    #[Test]
    public function it_encodes_supported_type_requests(): void
    {
        $this->assertSame(2, X32MonitorBusEqControlMap::typeFromRequest('PEQ'));
        $this->assertSame(2, X32MonitorBusEqControlMap::typeFromRequest(2));
    }
}
