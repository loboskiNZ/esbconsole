<?php

namespace Tests\Unit;

use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeX32ConsoleSnapshotReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_scene_numbers_produce_different_channel_fader_profiles(): void
    {
        $device = $this->createX32Device(Band::factory()->create());
        $reader = new FakeX32ConsoleSnapshotReader;

        $sceneOne = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '01',
            host: '127.0.0.1',
            port: 10023,
        ));

        $sceneFive = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '05',
            host: '127.0.0.1',
            port: 10023,
        ));

        $this->assertTrue($sceneOne->success);
        $this->assertTrue($sceneFive->success);
        $this->assertSame('01', $sceneOne->summary['scene_number']);
        $this->assertSame('05', $sceneFive->summary['scene_number']);
        $this->assertArrayNotHasKey('scene_name', $sceneOne->summary);
        $this->assertArrayNotHasKey('scene_name', $sceneFive->summary);
        $this->assertNotSame(
            $sceneOne->summary['channels'][0]['fader'],
            $sceneFive->summary['channels'][0]['fader'],
        );
        $this->assertNotSame(
            $sceneOne->summary['buses'][0]['fader'],
            $sceneFive->summary['buses'][0]['fader'],
        );
        $this->assertArrayHasKey('eq', $sceneOne->summary['buses'][0]);
        $this->assertArrayNotHasKey('eq', $sceneOne->summary['buses'][1]);
        $this->assertSame(79.6, $sceneOne->summary['buses'][0]['eq']['bands'][0]['f_hz']);
        $this->assertArrayHasKey('sends', $sceneOne->summary['channels'][0]);
        $this->assertSame(0.75, $sceneOne->summary['channels'][0]['sends']['buses'][1]['level']);
    }

    public function test_fixture_send_matrix_flows_into_configuration_block(): void
    {
        $device = $this->createX32Device(Band::factory()->create());
        $result = (new FakeX32ConsoleSnapshotReader)->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '01',
            host: '127.0.0.1',
            port: 10023,
        ));

        $attached = app(\App\Services\X32\X32ConfigurationLearnAssembler::class)->attach($result->summary);

        $this->assertSame(
            'learned',
            $attached['configuration']['channels'][0]['sends']['buses']['1']['level']['state'],
        );
    }

    private function createX32Device(Band $band): IntegrationDevice
    {
        $device = IntegrationDevice::factory()->create([
            'band_id' => $band->id,
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        return $device;
    }
}
