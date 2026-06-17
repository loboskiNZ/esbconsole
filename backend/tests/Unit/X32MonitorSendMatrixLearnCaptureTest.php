<?php

namespace Tests\Unit;

use App\Services\X32\X32MonitorSendMatrixLearnCapture;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorSendMatrixLearnCaptureTest extends TestCase
{
    #[Test]
    public function it_captures_channel_bus_send_matrix_from_seeded_osc_values(): void
    {
        $fixture = X32MonitorSendMatrixLearnCapture::fixtureChannelOne();
        $floats = [];
        $ints = [];

        foreach (X32MonitorSendMatrixLearnCapture::oscSeedsFromCapture($fixture) as $seed) {
            if (is_int($seed['value'])) {
                $ints[$seed['path']] = $seed['value'];
            } else {
                $floats[$seed['path']] = (float) $seed['value'];
            }
        }

        $capture = (new X32MonitorSendMatrixLearnCapture)->captureForChannel(
            1,
            fn (string $path): float => $floats[$path] ?? 0.0,
            fn (string $path): int => $ints[$path] ?? 0,
        );

        $this->assertTrue($capture['captured']);
        $this->assertSame(1, $capture['buses'][1]['on']);
        $this->assertSame(0.75, $capture['buses'][1]['level']);
        $this->assertEqualsWithDelta(0.0, $capture['buses'][1]['level_db'], 0.1);
        $this->assertSame('post_fader', $capture['buses'][1]['tap']);
        $this->assertArrayNotHasKey('pan', $capture['buses'][2]);
    }
}
