<?php

namespace Tests\Unit;

use App\Services\X32\X32BusEqLearnCapture;
use App\Services\X32\X32BusEqOscDecoder;
use App\Services\X32\X32OscAddressMap;
use App\Services\X32\X32OscParameterScale;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32BusEqOscDecoderTest extends TestCase
{
    #[Test]
    public function it_decodes_bus_eq_frequency_gain_and_q_scales(): void
    {
        $frequency = X32BusEqOscDecoder::decodeFrequency(
            X32BusEqOscDecoder::encodeFrequency(79.6),
        );
        $gain = X32BusEqOscDecoder::decodeGainDb(
            X32BusEqOscDecoder::encodeGainDb(0.0),
        );
        $q = X32BusEqOscDecoder::decodeQ(
            X32BusEqOscDecoder::encodeQ(2.0),
        );

        $this->assertEqualsWithDelta(79.6, $frequency, 0.5);
        $this->assertEqualsWithDelta(0.0, $gain, 0.26);
        $this->assertEqualsWithDelta(2.0, $q, 0.05);
    }

    #[Test]
    public function it_maps_osc_eq_type_enums_to_mode_labels(): void
    {
        $this->assertSame('LSHV', X32BusEqOscDecoder::typeToMode(1));
        $this->assertSame('PEQ', X32BusEqOscDecoder::typeToMode(2));
        $this->assertSame('LCUT', X32BusEqOscDecoder::typeToMode(0));
        $this->assertSame('BU12', X32BusEqOscDecoder::typeToMode(7));
        $this->assertFalse(X32BusEqOscDecoder::modeIsSupportedInMonitorCard('BU12'));
    }

    #[Test]
    public function fixture_bus_one_uses_documented_osc_paths(): void
    {
        $fixture = X32BusEqLearnCapture::fixtureBusOne();

        $this->assertSame(X32OscAddressMap::busEqOn(1), $fixture['osc_paths']['on']);
        $this->assertSame(X32OscAddressMap::busEqBandType(1, 3), $fixture['bands'][2]['osc_paths']['type']);
        $this->assertSame(0, $fixture['on']);
        $this->assertSame('LSHV', X32BusEqOscDecoder::typeToMode($fixture['bands'][0]['type']));
    }

    #[Test]
    public function logf_and_linf_helpers_round_trip_at_range_edges(): void
    {
        $this->assertEqualsWithDelta(
            20.0,
            X32OscParameterScale::decodeLogf(0.0, 20.0, 20000.0, 201),
            0.01,
        );
        $this->assertEqualsWithDelta(
            20000.0,
            X32OscParameterScale::decodeLogf(1.0, 20.0, 20000.0, 201),
            1.0,
        );
        $this->assertEqualsWithDelta(
            -15.0,
            X32OscParameterScale::decodeLinf(0.0, -15.0, 15.0, 0.25),
            0.01,
        );
        $this->assertEqualsWithDelta(
            15.0,
            X32OscParameterScale::decodeLinf(1.0, -15.0, 15.0, 0.25),
            0.01,
        );
    }
}
