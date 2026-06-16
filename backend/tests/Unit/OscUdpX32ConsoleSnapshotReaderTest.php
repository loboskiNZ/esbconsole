<?php

namespace Tests\Unit;

use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\Models\IntegrationDevice;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\OscUdpX32ConsoleSnapshotReader;
use App\Services\X32\RoutingX32ConsoleSnapshotReader;
use App\Services\X32\X32RoutingLearnCapture;
use App\Services\X32\X32RoutingOscAddressMap;
use App\Services\X32\X32OscMessageCodec;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32RuntimeModeResolver;
use App\Services\X32\X32SceneParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OscUdpX32ConsoleSnapshotReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalls_scene_and_reads_channel_state_from_osc_client(): void
    {
        $device = IntegrationDevice::factory()->create([
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_LIVE],
        ]);

        $fakeOsc = new FakeX32OscConsoleClient;
        $this->seedDeskState($fakeOsc);

        $reader = new OscUdpX32ConsoleSnapshotReader(
            $fakeOsc,
            new X32OscMessageCodec,
            new X32OscSceneRecallPacketBuilder,
            new X32SceneParameterResolver,
            new X32RoutingLearnCapture,
            sceneSettleMs: 0,
        );

        $result = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '01',
            host: '192.168.1.100',
            port: 10023,
        ));

        $this->assertTrue($result->success);
        $this->assertSame('live_osc', $result->summary['transport']);
        $this->assertSame('Kick', $result->summary['channels'][0]['name']);
        $this->assertSame(0.63, $result->summary['channels'][0]['fader']);
        $this->assertSame(1, $result->summary['channels'][0]['color']);
        $this->assertTrue($result->summary['channels'][0]['controls']['gate_on']);
        $this->assertSame(0.42, $result->summary['channels'][0]['controls']['pan']);
        $this->assertTrue($result->summary['channels'][0]['controls']['main_lr']);
        $this->assertArrayHasKey('raw_osc', $result->summary['routing']);
        $this->assertArrayHasKey('normalized', $result->summary['routing']);
        $this->assertSame('not_learned', $result->summary['routing']['normalized']['main_lr']['state']);
        $this->assertArrayNotHasKey('main_lr', $result->summary['routing']);
        $this->assertNotEmpty($fakeOsc->writes());
    }

    public function test_dry_run_device_fails_when_live_routing_is_forced(): void
    {
        $device = IntegrationDevice::factory()->create([
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_DRY_RUN],
        ]);

        $router = new RoutingX32ConsoleSnapshotReader(
            new FakeX32ConsoleSnapshotReader,
            new OscUdpX32ConsoleSnapshotReader(
                new FakeX32OscConsoleClient,
                new X32OscMessageCodec,
                new X32OscSceneRecallPacketBuilder,
                new X32SceneParameterResolver,
                new X32RoutingLearnCapture,
                sceneSettleMs: 0,
            ),
            new X32RuntimeModeResolver,
            allowLiveRoutingInTests: true,
        );

        $result = $router->learnScene(new X32ConsoleLearnCommand(
            device: $device->fresh('integrationConnectionProfiles'),
            requestedSceneNumber: '01',
            host: '192.168.1.100',
            port: 10023,
        ));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('runtime_mode', $result->errors[0]);
    }

    private function seedDeskState(FakeX32OscConsoleClient $fakeOsc): void
    {
        $fakeOsc->seedFloat('/ch/01/mix/fader', 0.63);
        $fakeOsc->seedInt('/ch/01/mix/on', 1);
        $fakeOsc->seedString('/ch/01/config/name', 'Kick');
        $fakeOsc->seedInt('/ch/01/config/color', 1);
        $fakeOsc->seedInt('/ch/01/gate/on', 1);
        $fakeOsc->seedInt('/ch/01/dyn/on', 0);
        $fakeOsc->seedInt('/ch/01/eq/on', 1);
        $fakeOsc->seedFloat('/ch/01/mix/pan', 0.42);
        $fakeOsc->seedInt('/ch/01/mix/st', 1);

        for ($index = 2; $index <= 32; $index++) {
            $this->seedDefaultChannel($fakeOsc, $index);
        }

        for ($index = 1; $index <= 16; $index++) {
            $fakeOsc->seedFloat(sprintf('/bus/%02d/mix/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/bus/%02d/mix/on', $index), 1);
            $fakeOsc->seedString(sprintf('/bus/%02d/config/name', $index), sprintf('Bus %02d', $index));
            $fakeOsc->seedInt(sprintf('/bus/%02d/config/color', $index), 3);
        }

        for ($index = 1; $index <= 8; $index++) {
            $fakeOsc->seedFloat(sprintf('/dca/%d/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/dca/%d/on', $index), 1);
        }

        for ($index = 1; $index <= 6; $index++) {
            $fakeOsc->seedFloat(sprintf('/mtx/%02d/mix/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/mtx/%02d/mix/on', $index), 1);
        }

        $fakeOsc->seedInt('/config/routing/routswitch', 0);
        $fakeOsc->seedInt('/config/routing/IN/1-8', 0);
        $fakeOsc->seedInt('/config/routing/IN/9-16', 0);
        $fakeOsc->seedInt('/config/routing/IN/17-24', 0);
        $fakeOsc->seedInt('/config/routing/IN/25-32', 0);
        $fakeOsc->seedInt('/config/routing/CARD/1-8', 0);
        $fakeOsc->seedInt('/config/routing/CARD/9-16', 0);
        $fakeOsc->seedInt('/config/routing/CARD/17-24', 0);
        $fakeOsc->seedInt('/config/routing/CARD/25-32', 0);
        $fakeOsc->seedInt('/config/routing/OUT/1-4', 0);
        $fakeOsc->seedInt('/config/routing/OUT/5-8', 0);
        $fakeOsc->seedInt('/config/routing/OUT/9-12', 0);
        $fakeOsc->seedInt('/config/routing/OUT/13-16', 0);

        foreach (X32RoutingOscAddressMap::outputMainSrcPaths() as $path) {
            $fakeOsc->seedInt($path, 0);
        }
    }

    private function seedDefaultChannel(FakeX32OscConsoleClient $fakeOsc, int $index): void
    {
        $fakeOsc->seedFloat(sprintf('/ch/%02d/mix/fader', $index), 0.5);
        $fakeOsc->seedInt(sprintf('/ch/%02d/mix/on', $index), 1);
        $fakeOsc->seedString(sprintf('/ch/%02d/config/name', $index), sprintf('CH %02d', $index));
        $fakeOsc->seedInt(sprintf('/ch/%02d/config/color', $index), 2);
        $fakeOsc->seedInt(sprintf('/ch/%02d/gate/on', $index), 0);
        $fakeOsc->seedInt(sprintf('/ch/%02d/dyn/on', $index), 0);
        $fakeOsc->seedInt(sprintf('/ch/%02d/eq/on', $index), 1);
        $fakeOsc->seedFloat(sprintf('/ch/%02d/mix/pan', $index), 0.5);
        $fakeOsc->seedInt(sprintf('/ch/%02d/mix/st', $index), 1);
    }
}
