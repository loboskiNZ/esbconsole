<?php

namespace Tests\Unit;

use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleMonitorSendControlService;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32FaderScale;
use App\Services\X32\X32MonitorSendControlMap;
use App\Services\X32\X32OscAddressMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32MonitorSendControlMapTest extends TestCase
{
    #[Test]
    public function it_maps_send_level_and_mute_to_documented_bus_scoped_paths(): void
    {
        $this->assertSame('/ch/03/mix/07/level', X32MonitorSendControlMap::oscPath(3, 7, 'level'));
        $this->assertSame('/ch/03/mix/07/on', X32MonitorSendControlMap::oscPath(3, 7, 'mute'));
        $this->assertNotSame(
            X32OscAddressMap::channelFader(3),
            X32MonitorSendControlMap::oscPath(3, 7, 'level'),
        );
    }

    #[Test]
    public function it_quantizes_level_writes_with_x32_fader_scale(): void
    {
        $linear = X32MonitorSendControlMap::levelLinearFromRequest(0.749);

        $this->assertEqualsWithDelta(
            X32FaderScale::quantizeLinear(0.749),
            $linear,
            0.0001,
        );
    }

    #[Test]
    public function it_inverts_monitor_mute_semantics_for_send_on(): void
    {
        $this->assertSame(0, X32MonitorSendControlMap::muteToSendOn(true));
        $this->assertSame(1, X32MonitorSendControlMap::muteToSendOn(false));
        $this->assertTrue(X32MonitorSendControlMap::sendOnToMuted(0));
        $this->assertFalse(X32MonitorSendControlMap::sendOnToMuted(1));
    }
}
