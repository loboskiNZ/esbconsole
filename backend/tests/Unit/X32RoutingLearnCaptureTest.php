<?php

namespace Tests\Unit;

use App\DataTransferObjects\X32\X32ConsoleLearnCommand;
use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use App\Services\X32\FakeX32OscConsoleClient;
use App\Services\X32\OscUdpX32ConsoleSnapshotReader;
use App\Services\X32\X32OscMessageCodec;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32RoutingLearnCapture;
use App\Services\X32\X32RoutingOscAddressMap;
use App\Services\X32\X32SceneParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32RoutingLearnCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_preserves_raw_routing_responses(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues([
            '/config/routing/CARD/1-8' => 20,
        ]));

        $this->assertSame('test', $result['source']);
        $this->assertArrayHasKey('raw_osc', $result);
        $this->assertSame(0, $result['raw_osc']['routswitch']['value']);
        $this->assertCount(4, $result['raw_osc']['input_banks']);
        $this->assertSame(4, $result['raw_osc']['input_banks'][0]['value']);
        $this->assertSame('/config/routing/IN/1-8', $result['raw_osc']['input_banks'][0]['path']);
        $this->assertCount(4, $result['raw_osc']['card']);
        $this->assertCount(4, $result['raw_osc']['out_1_16']);
        $this->assertCount(16, $result['raw_osc']['main_output_patch']);
    }

    public function test_capture_builds_normalized_input_banks_and_card_inputs(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues());

        $banks = $result['normalized']['input_banks'];
        $this->assertSame('A1-8', $banks[0]['raw_label']);
        $this->assertSame('aes50_a', $banks[0]['source_type']);
        $this->assertSame('B1-8', $banks[2]['raw_label']);
        $this->assertSame('CARD1-8', $banks[3]['raw_label']);
        $this->assertSame('card_usb', $banks[3]['source_type']);

        $cardInputs = $result['normalized']['card_inputs'];
        $inputBankCards = array_values(array_filter($cardInputs, fn ($e) => $e['context'] === 'input_bank'));
        $routingTableCards = array_values(array_filter($cardInputs, fn ($e) => $e['context'] === 'card_routing_table'));

        $this->assertCount(1, $inputBankCards);
        $this->assertSame('input_bank', $inputBankCards[0]['context']);
        $this->assertSame('CARD1-8', $inputBankCards[0]['card_range']);
        $this->assertSame('CH 25–32', $inputBankCards[0]['desk_channel_range']);
        $this->assertCount(4, $routingTableCards);
    }

    public function test_unknown_routing_index_is_preserved_not_discarded(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues([
            X32RoutingOscAddressMap::ROUTSWITCH => 99,
            '/config/routing/IN/1-8' => 99,
            '/config/routing/OUT/1-4' => 99,
        ]));

        $this->assertSame(99, $result['raw_osc']['input_banks'][0]['value']);
        $this->assertSame('UNKNOWN(99)', $result['normalized']['input_banks'][0]['raw_label']);
        $this->assertSame('unknown', $result['normalized']['input_banks'][0]['source_type']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_missing_routing_paths_default_to_zero_and_are_preserved(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->capture('test', fn (string $path): int => 0);

        $this->assertSame('AN1-8', $result['normalized']['input_banks'][0]['raw_label']);
        $this->assertSame(0, $result['raw_osc']['card'][0]['value']);
        $this->assertSame('not_learned', $result['normalized']['main_lr']['state']);
    }

    public function test_main_lr_is_not_learned_when_output_patch_is_off(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues());

        $mainLr = $result['normalized']['main_lr'];
        $this->assertSame('not_learned', $mainLr['state']);
        $this->assertFalse($mainLr['learned']);
        $this->assertNull($mainLr['left']);
        $this->assertNull($mainLr['right']);
    }

    public function test_main_lr_learned_from_outputs_other_than_15_and_16(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues([
            X32RoutingOscAddressMap::outputMainSrcPath(3) => 1,
            X32RoutingOscAddressMap::outputMainSrcPath(4) => 2,
        ]));

        $mainLr = $result['normalized']['main_lr'];
        $this->assertSame('learned', $mainLr['state']);
        $this->assertTrue($mainLr['learned']);
        $this->assertSame(3, $mainLr['left']['output_number']);
        $this->assertSame('Main L', $mainLr['left']['raw_label']);
        $this->assertSame(4, $mainLr['right']['output_number']);
        $this->assertSame('Main R', $mainLr['right']['raw_label']);
        $this->assertNotSame(15, $mainLr['left']['output_number']);
        $this->assertNotSame(16, $mainLr['right']['output_number']);
    }

    public function test_main_lr_can_be_learned_on_out_15_16_when_console_reports_it(): void
    {
        $capture = new X32RoutingLearnCapture;

        $result = $capture->captureFromRawValues('test', $this->sampleRoutingRawValues([
            X32RoutingOscAddressMap::outputMainSrcPath(15) => 1,
            X32RoutingOscAddressMap::outputMainSrcPath(16) => 2,
        ]));

        $mainLr = $result['normalized']['main_lr'];
        $this->assertSame('learned', $mainLr['state']);
        $this->assertSame(15, $mainLr['left']['output_number']);
        $this->assertSame(16, $mainLr['right']['output_number']);
    }

    public function test_routing_learn_does_not_invent_top_level_main_lr_or_foh(): void
    {
        $device = $this->createX32Device(Band::factory()->create());
        $reader = new FakeX32ConsoleSnapshotReader;

        $result = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '01',
            host: '127.0.0.1',
            port: 10023,
        ));

        $routing = $result->summary['routing'];
        $this->assertArrayNotHasKey('main_lr', $routing);
        $this->assertArrayNotHasKey('foh', $routing);
        $this->assertArrayHasKey('normalized', $routing);
        $this->assertArrayHasKey('main_lr', $routing['normalized']);
    }

    public function test_fake_console_learn_includes_routing_in_summary(): void
    {
        $device = $this->createX32Device(Band::factory()->create());
        $reader = new FakeX32ConsoleSnapshotReader;

        $result = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '01',
            host: '127.0.0.1',
            port: 10023,
        ));

        $this->assertTrue($result->success);
        $routing = $result->summary['routing'];
        $this->assertArrayHasKey('raw_osc', $routing);
        $this->assertArrayHasKey('normalized', $routing);
        $this->assertSame('A1-8', $routing['normalized']['input_banks'][0]['raw_label']);
        $this->assertNotEmpty($routing['normalized']['out_1_16']);
        $this->assertSame('learned', $routing['normalized']['main_lr']['state']);
        $this->assertSame(3, $routing['normalized']['main_lr']['left']['output_number']);
        $this->assertSame(4, $routing['normalized']['main_lr']['right']['output_number']);
    }

    public function test_fixture_scene_without_main_lr_leaves_main_lr_not_learned(): void
    {
        $device = $this->createX32Device(Band::factory()->create());
        $reader = new FakeX32ConsoleSnapshotReader;

        $result = $reader->learnScene(new X32ConsoleLearnCommand(
            device: $device,
            requestedSceneNumber: '05',
            host: '127.0.0.1',
            port: 10023,
        ));

        $this->assertSame('not_learned', $result->summary['routing']['normalized']['main_lr']['state']);
    }

    public function test_learned_routing_persists_in_baseline_json(): void
    {
        $band = Band::factory()->create();
        $device = $this->createX32Device($band);
        $show = \App\Models\Show::factory()->forBand($band)->create();

        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Routing Baseline');

        $routing = $baseline->baseline_json['routing'] ?? [];
        $this->assertArrayHasKey('raw_osc', $routing);
        $this->assertArrayHasKey('normalized', $routing);
        $this->assertSame('A1-8', $routing['normalized']['input_banks'][0]['raw_label']);
        $this->assertSame('learned', $routing['normalized']['main_lr']['state']);
        $this->assertSame(3, $routing['normalized']['main_lr']['left']['output_number']);
    }

    public function test_live_osc_reader_captures_routing_from_seeded_client(): void
    {
        $device = IntegrationDevice::factory()->create([
            'configuration' => ['runtime_mode' => 'live'],
        ]);

        $fakeOsc = new FakeX32OscConsoleClient;
        $this->seedMinimalDeskAndRouting($fakeOsc);

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
        $this->assertArrayHasKey('raw_osc', $result->summary['routing']);
        $this->assertSame('CARD1-8', $result->summary['routing']['normalized']['input_banks'][3]['raw_label']);
    }

    private function seedMinimalDeskAndRouting(FakeX32OscConsoleClient $fakeOsc): void
    {
        for ($index = 1; $index <= 32; $index++) {
            $fakeOsc->seedFloat(sprintf('/ch/%02d/mix/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/ch/%02d/mix/on', $index), 1);
            $fakeOsc->seedString(sprintf('/ch/%02d/config/name', $index), sprintf('CH %02d', $index));
            $fakeOsc->seedInt(sprintf('/ch/%02d/config/color', $index), 1);
            $fakeOsc->seedInt(sprintf('/ch/%02d/gate/on', $index), 0);
            $fakeOsc->seedInt(sprintf('/ch/%02d/dyn/on', $index), 0);
            $fakeOsc->seedInt(sprintf('/ch/%02d/eq/on', $index), 1);
            $fakeOsc->seedFloat(sprintf('/ch/%02d/mix/pan', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/ch/%02d/mix/st', $index), 1);
        }

        for ($index = 1; $index <= 16; $index++) {
            $fakeOsc->seedFloat(sprintf('/bus/%02d/mix/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/bus/%02d/mix/on', $index), 1);
            $fakeOsc->seedString(sprintf('/bus/%02d/config/name', $index), sprintf('Bus %02d', $index));
            $fakeOsc->seedInt(sprintf('/bus/%02d/config/color', $index), 1);
        }

        for ($index = 1; $index <= 8; $index++) {
            $fakeOsc->seedFloat(sprintf('/dca/%d/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/dca/%d/on', $index), 1);
        }

        for ($index = 1; $index <= 6; $index++) {
            $fakeOsc->seedFloat(sprintf('/mtx/%02d/mix/fader', $index), 0.5);
            $fakeOsc->seedInt(sprintf('/mtx/%02d/mix/on', $index), 1);
        }

        $fakeOsc->seedInt(X32RoutingOscAddressMap::ROUTSWITCH, 0);
        $fakeOsc->seedInt('/config/routing/IN/1-8', 4);
        $fakeOsc->seedInt('/config/routing/IN/9-16', 5);
        $fakeOsc->seedInt('/config/routing/IN/17-24', 10);
        $fakeOsc->seedInt('/config/routing/IN/25-32', 16);
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

        $fakeOsc->seedInt(X32RoutingOscAddressMap::outputMainSrcPath(3), 1);
        $fakeOsc->seedInt(X32RoutingOscAddressMap::outputMainSrcPath(4), 2);
    }

    /**
     * @param  array<string, int>  $overrides
     * @return array<string, int>
     */
    private function sampleRoutingRawValues(array $overrides = []): array
    {
        $values = [
            X32RoutingOscAddressMap::ROUTSWITCH => 0,
            '/config/routing/IN/1-8' => 4,
            '/config/routing/IN/9-16' => 5,
            '/config/routing/IN/17-24' => 10,
            '/config/routing/IN/25-32' => 16,
            '/config/routing/CARD/1-8' => 0,
            '/config/routing/CARD/9-16' => 0,
            '/config/routing/CARD/17-24' => 0,
            '/config/routing/CARD/25-32' => 0,
            '/config/routing/OUT/1-4' => 0,
            '/config/routing/OUT/5-8' => 0,
            '/config/routing/OUT/9-12' => 0,
            '/config/routing/OUT/13-16' => 0,
        ];

        foreach (X32RoutingOscAddressMap::outputMainSrcPaths() as $path) {
            $values[$path] = 0;
        }

        return array_merge($values, $overrides);
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
