<?php

namespace Tests\Unit;

use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\X32OscAddressMap;
use App\Services\X32\X32RuntimeModeResolver;
use App\Services\X32\X32SceneMetadataService;
use App\Services\X32\X32SceneParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X32SceneMetadataServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enriches_summary_with_live_scene_name_when_missing_from_baseline(): void
    {
        $device = $this->createLiveDevice();
        $fakeOsc = new FakeX32OscConsoleClient;
        $fakeOsc->seedString(X32OscAddressMap::sceneShowfileName(2), 'Desk Scene 02');

        $service = new X32SceneMetadataService(
            $fakeOsc,
            new X32RuntimeModeResolver,
            new X32SceneParameterResolver,
        );

        $summary = [
            'scene_number' => '02',
            'routing' => ['scene_recalled' => 2],
        ];

        $enriched = $service->enrichSummaryWithSceneName($summary, $device);

        $this->assertSame('Desk Scene 02', $enriched['scene_name']);
        $this->assertSame('Desk Scene 02', $enriched['routing']['scene_name']);
    }

    #[Test]
    public function it_does_not_overwrite_existing_scene_name(): void
    {
        $device = $this->createLiveDevice();
        $fakeOsc = new FakeX32OscConsoleClient;
        $fakeOsc->seedString(X32OscAddressMap::sceneShowfileName(2), 'Live Desk Name');

        $service = new X32SceneMetadataService(
            $fakeOsc,
            new X32RuntimeModeResolver,
            new X32SceneParameterResolver,
        );

        $summary = [
            'scene_number' => '02',
            'scene_name' => 'Stored Baseline Name',
        ];

        $enriched = $service->enrichSummaryWithSceneName($summary, $device);

        $this->assertSame('Stored Baseline Name', $enriched['scene_name']);
    }

    private function createLiveDevice(): IntegrationDevice
    {
        $device = IntegrationDevice::factory()->forBand(Band::factory()->create())->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_LIVE],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        return $device->fresh('integrationConnectionProfiles');
    }
}
