<?php

namespace Tests\Unit;

use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleMonitorBusEqControlService;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32BusEqOscDecoder;
use App\Services\X32\X32MonitorBusEqControlMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowConsoleMonitorBusEqControlServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_and_confirms_bus_eq_master_on(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusEqControlMap::oscPath(2, X32MonitorBusEqControlMap::PARAMETER_ON);
        $fakeOsc->seedInt($path, 0);

        $service = app(ShowConsoleMonitorBusEqControlService::class);
        $result = $service->updateEq($show, 2, null, X32MonitorBusEqControlMap::PARAMETER_ON, true);

        $this->assertTrue($result['success']);
        $this->assertSame('/bus/02/eq/on', $result['osc_path']);
        $this->assertSame(2, $result['bus']);
        $this->assertNull($result['band']);
        $this->assertSame(1, $result['confirmed_value']);
        $this->assertSame('ON', $result['display_value']);
        $this->assertCount(1, $fakeOsc->writes());
    }

    #[Test]
    public function it_writes_and_confirms_band_gain_with_encoded_read_back(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_GAIN, 3);
        $encoded = X32BusEqOscDecoder::encodeGainDb(3.0);
        $fakeOsc->seedFloat($path, 0.0);

        $service = app(ShowConsoleMonitorBusEqControlService::class);
        $result = $service->updateEq($show, 1, 3, X32MonitorBusEqControlMap::PARAMETER_GAIN, 3.0);

        $this->assertTrue($result['success']);
        $this->assertSame('/bus/01/eq/3/g', $result['osc_path']);
        $this->assertSame(3, $result['band']);
        $this->assertEqualsWithDelta($encoded, $result['encoded_osc_value'], 0.0001);
        $this->assertEqualsWithDelta($encoded, $result['confirmed_value'], 0.0001);
        $this->assertSame('+3.0', $result['display_value']);
    }

    #[Test]
    public function it_fails_when_read_back_does_not_confirm_write(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusEqControlMap::oscPath(1, X32MonitorBusEqControlMap::PARAMETER_FREQUENCY, 1);
        $fakeOsc->seedFloat($path, 0.1);
        $fakeOsc->queryFailPaths[] = $path;

        $service = app(ShowConsoleMonitorBusEqControlService::class);
        $result = $service->updateEq($show, 1, 1, X32MonitorBusEqControlMap::PARAMETER_FREQUENCY, 500.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Monitor bus EQ write failed', (string) $result['error']);
    }

    #[Test]
    public function it_blocks_writes_when_runtime_mode_is_not_live(): void
    {
        [$show] = $this->showWithLiveDevice(runtimeMode: 'dry_run');

        $service = app(ShowConsoleMonitorBusEqControlService::class);
        $result = $service->updateEq($show, 1, null, X32MonitorBusEqControlMap::PARAMETER_ON, true);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Live X32 control is not enabled', (string) $result['error']);
    }

    /**
     * @return array{0: Show, 1: FakeX32OscConsoleClient}
     */
    private function showWithLiveDevice(string $runtimeMode = 'live'): array
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => $runtimeMode],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        $summary = ['device_name' => 'FOH X32', 'scene_number' => '01', 'channels' => [], 'buses' => []];
        $snapshot = ConsoleLearningSnapshot::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'integration_device_id' => $device->id,
            'learning_status' => ConsoleLearningStatus::Saved,
            'learned_summary_json' => $summary,
        ]);

        ShowConsoleBaseline::factory()->create([
            'band_id' => $band->id,
            'show_id' => $show->id,
            'source_snapshot_id' => $snapshot->id,
            'baseline_json' => $summary,
        ]);

        $fakeOsc = app(FakeX32OscConsoleClient::class);
        $fakeOsc->queryFailPaths = [];
        $fakeOsc->shouldFail = false;

        return [$show, $fakeOsc];
    }
}
