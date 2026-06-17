<?php

namespace Tests\Unit;

use App\Services\X32\X32BusEqLearnCapture;
use App\Services\X32\X32OscAddressMap;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32BusEqLearnCaptureTest extends TestCase
{
    #[Test]
    public function it_captures_bus_master_eq_from_seeded_osc_values(): void
    {
        $fixture = X32BusEqLearnCapture::fixtureBusOne();
        $floats = [];
        $ints = [];

        foreach (X32BusEqLearnCapture::oscSeedsFromCapture($fixture) as $seed) {
            if (is_int($seed['value'])) {
                $ints[$seed['path']] = $seed['value'];
            } else {
                $floats[$seed['path']] = (float) $seed['value'];
            }
        }

        $capture = (new X32BusEqLearnCapture)->capture(
            1,
            fn (string $path): float => $floats[$path] ?? 0.0,
            fn (string $path): int => $ints[$path] ?? 0,
        );

        $this->assertTrue($capture['captured']);
        $this->assertSame(0, $capture['on']);
        $this->assertCount(6, $capture['bands']);
        $this->assertSame(79.6, $capture['bands'][0]['f_hz']);
        $this->assertSame(0.0, $capture['bands'][0]['g_db']);
        $this->assertEqualsWithDelta(1.96, $capture['bands'][0]['q'], 0.01);
        $this->assertSame(X32OscAddressMap::busEqBandGain(1, 1), $capture['bands'][0]['osc_paths']['gain']);
    }
}
