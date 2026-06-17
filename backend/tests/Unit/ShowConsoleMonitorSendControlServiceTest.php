<?php

namespace Tests\Unit;

use App\Enums\ConsoleLearningStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowConsoleMonitorSendControlServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_and_confirms_send_level_on_selected_bus_only(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorSendControlMap::oscPath(2, 5, 'level');
        $fakeOsc->seedFloat($path, 0.5);

        $service = app(ShowConsoleMonitorSendControlService::class);
        $result = $service->updateSend($show, 5, 2, 'level', 0.75);

        $this->assertTrue($result['success']);
        $this->assertSame('/ch/02/mix/05/level', $result['osc_path']);
        $this->assertSame(2, $result['channel']);
        $this->assertSame(5, $result['bus']);
        $this->assertEqualsWithDelta(0.75, $result['confirmed_value'], 0.002);
        $this->assertSame('0.0', $result['display_value']);
        $this->assertCount(1, $fakeOsc->writes());
        $this->assertSame('float', $fakeOsc->writes()[0]['type']);
        $this->assertEqualsWithDelta(
            X32FaderScale::quantizeLinear(0.75),
            $fakeOsc->writes()[0]['value'],
            0.0001,
        );
    }

    #[Test]
    public function it_writes_and_confirms_send_mute_using_inverted_send_on_semantics(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorSendControlMap::oscPath(4, 1, 'mute');
        $fakeOsc->seedInt($path, 1);

        $service = app(ShowConsoleMonitorSendControlService::class);
        $result = $service->updateSend($show, 1, 4, 'mute', true);

        $this->assertTrue($result['success']);
        $this->assertSame('/ch/04/mix/01/on', $result['osc_path']);
        $this->assertTrue($result['requested_value']);
        $this->assertTrue($result['confirmed_value']);
        $this->assertSame('Muted', $result['display_value']);
        $this->assertSame(0, $fakeOsc->writes()[0]['value']);
    }

    #[Test]
    public function it_fails_when_read_back_does_not_confirm_write(): void
    {
        [$show, $fakeOsc] = $this->showWithLiveDevice();
        $path = X32MonitorSendControlMap::oscPath(1, 1, 'level');
        $fakeOsc->seedFloat($path, 0.5);
        $fakeOsc->queryFailPaths[] = $path;

        $service = app(ShowConsoleMonitorSendControlService::class);
        $result = $service->updateSend($show, 1, 1, 'level', 0.75);

        $this->assertFalse($result['success']);
        $this->assertSame('Monitor send write failed: Fake X32 OSC read-back failed for /ch/01/mix/01/level', $result['error']);
    }

    #[Test]
    public function it_blocks_writes_when_runtime_mode_is_not_live(): void
    {
        [$show] = $this->showWithLiveDevice(runtimeMode: 'dry_run');

        $service = app(ShowConsoleMonitorSendControlService::class);
        $result = $service->updateSend($show, 1, 1, 'level', 0.75);

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
