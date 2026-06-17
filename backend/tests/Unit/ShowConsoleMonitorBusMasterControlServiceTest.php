<?php

namespace Tests\Unit;

use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleMonitorBusMasterControlService;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32FaderScale;
use App\Services\X32\X32MonitorBusMasterControlMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowConsoleMonitorBusMasterControlServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_and_confirms_bus_master_fader_using_x32_fader_scale(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusMasterControlMap::oscPath(2, X32MonitorBusMasterControlMap::PARAMETER_LEVEL);
        $fakeOsc->seedFloat($path, 0.5);

        $service = app(ShowConsoleMonitorBusMasterControlService::class);
        $result = $service->updateMaster($show, 2, X32MonitorBusMasterControlMap::PARAMETER_LEVEL, 0.75);

        $this->assertTrue($result['success']);
        $this->assertSame('/bus/02/mix/fader', $result['osc_path']);
        $this->assertSame(2, $result['bus']);
        $this->assertEqualsWithDelta(0.75, $result['confirmed_value'], 0.002);
        $this->assertEqualsWithDelta(
            X32FaderScale::quantizeLinear(0.75),
            $fakeOsc->writes()[0]['value'],
            0.0001,
        );
    }

    #[Test]
    public function it_writes_and_confirms_bus_master_mute_using_bus_on_semantics(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusMasterControlMap::oscPath(4, X32MonitorBusMasterControlMap::PARAMETER_MUTE);
        $fakeOsc->seedInt($path, 1);

        $service = app(ShowConsoleMonitorBusMasterControlService::class);
        $result = $service->updateMaster($show, 4, X32MonitorBusMasterControlMap::PARAMETER_MUTE, true);

        $this->assertTrue($result['success']);
        $this->assertSame('/bus/04/mix/on', $result['osc_path']);
        $this->assertTrue($result['requested_value']);
        $this->assertSame(0, $result['encoded_osc_value']);
        $this->assertSame(0, $result['confirmed_value']);
        $this->assertSame('Muted', $result['display_value']);
    }

    #[Test]
    public function it_fails_when_read_back_does_not_confirm_write(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorBusMasterControlMap::oscPath(1, X32MonitorBusMasterControlMap::PARAMETER_LEVEL);
        $fakeOsc->seedFloat($path, 0.5);
        $fakeOsc->queryFailPaths[] = $path;

        $service = app(ShowConsoleMonitorBusMasterControlService::class);
        $result = $service->updateMaster($show, 1, X32MonitorBusMasterControlMap::PARAMETER_LEVEL, 0.75);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Monitor bus master write failed', (string) $result['error']);
    }

    #[Test]
    public function it_blocks_writes_when_runtime_mode_is_not_live(): void
    {
        [$show] = $this->showWithLiveDevice(runtimeMode: 'dry_run');

        $service = app(ShowConsoleMonitorBusMasterControlService::class);
        $result = $service->updateMaster($show, 1, X32MonitorBusMasterControlMap::PARAMETER_LEVEL, 0.75);

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
