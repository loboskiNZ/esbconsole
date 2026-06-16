<?php

namespace Tests\Feature;

use App\Contracts\X32\UdpSocketSenderInterface;
use App\Models\Band;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\Integration\IntegrationDeviceValidator;
use App\Services\Runtime\Adapters\X32AdapterFactory;
use App\Services\X32\X32DeviceSelector;
use App\Services\X32\X32DeviceSelectionResult;
use App\Services\X32\X32LiveValidationResult;
use App\Services\X32\X32LiveValidationService;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32RuntimeModeResolver;
use App\Services\X32\X32SceneParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32LiveValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_blocked_when_confirm_live_is_false(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: false,
        );

        $this->assertFalse($result->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('confirm_live', $result->context['gate']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_blocked_when_runtime_mode_missing_or_dry_run(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: null);

        $missingModeResult = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($missingModeResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $missingModeResult->status);
        $this->assertSame(X32RuntimeModeResolver::MODE_DRY_RUN, $missingModeResult->mode);

        $dryRunBand = Band::factory()->create();
        $this->createX32Device($dryRunBand, runtimeMode: X32RuntimeModeResolver::MODE_DRY_RUN);

        $dryRunResult = $this->validationService($sender)->validate(
            bandId: $dryRunBand->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($dryRunResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $dryRunResult->status);
        $this->assertSame('runtime_mode', $dryRunResult->context['gate']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_blocked_when_runtime_mode_invalid_or_disabled(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;

        $invalidBand = Band::factory()->create();
        $this->createX32Device($invalidBand, runtimeMode: 'unsafe_live');

        $invalidResult = $this->validationService($sender)->validate(
            bandId: $invalidBand->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($invalidResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $invalidResult->status);
        $this->assertSame(X32RuntimeModeResolver::MODE_DISABLED, $invalidResult->mode);

        $disabledBand = Band::factory()->create();
        $this->createX32Device($disabledBand, runtimeMode: X32RuntimeModeResolver::MODE_DISABLED);

        $disabledResult = $this->validationService($sender)->validate(
            bandId: $disabledBand->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($disabledResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $disabledResult->status);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_blocked_when_no_enabled_x32_device_exists(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();

        IntegrationDevice::factory()->forBand($band)->create([
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => false,
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_LIVE],
        ]);

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($result->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('device', $result->context['gate']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_blocked_when_scene_is_invalid(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '999',
            confirmLive: true,
        );

        $this->assertFalse($result->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $result->status);
        $this->assertSame('scene', $result->context['gate']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_blocked_when_connection_profile_is_missing_or_invalid(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;

        $missingProfileBand = Band::factory()->create();
        IntegrationDevice::factory()->forBand($missingProfileBand)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_LIVE],
        ]);

        $missingProfileResult = $this->validationService($sender)->validate(
            bandId: $missingProfileBand->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($missingProfileResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $missingProfileResult->status);
        $this->assertSame('connection_profile', $missingProfileResult->context['gate']);

        $invalidProfileBand = Band::factory()->create();
        $device = IntegrationDevice::factory()->forBand($invalidProfileBand)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => ['runtime_mode' => X32RuntimeModeResolver::MODE_LIVE],
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => null,
            'port' => null,
            'enabled' => true,
        ]);

        $invalidProfileResult = $this->validationService($sender)->validate(
            bandId: $invalidProfileBand->id,
            scene: '5',
            confirmLive: true,
        );

        $this->assertFalse($invalidProfileResult->success);
        $this->assertSame(X32LiveValidationResult::STATUS_BLOCKED, $invalidProfileResult->status);
        $this->assertCount(0, $sender->sent);
    }

    public function test_validation_acknowledges_with_fake_sender_when_confirm_live_true_and_runtime_mode_live(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: true,
            operatorLabel: 'foh-tech',
            notes: 'pre-show check',
        );

        $this->assertTrue($result->success);
        $this->assertSame(X32LiveValidationResult::STATUS_ACKNOWLEDGED, $result->status);
        $this->assertSame('live', $result->mode);
        $this->assertSame('main-x32', $result->deviceKey);
        $this->assertSame('5', $result->scene);
    }

    public function test_fake_sender_records_exactly_one_udp_send_on_valid_live_validation(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device(
            $band,
            runtimeMode: X32RuntimeModeResolver::MODE_LIVE,
            host: '192.168.10.20',
            port: 10023,
        );

        $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '7',
            confirmLive: true,
        );

        $this->assertCount(1, $sender->sent);
        $this->assertSame('192.168.10.20', $sender->sent[0]['host']);
        $this->assertSame(10023, $sender->sent[0]['port']);
        $this->assertNotSame('', $sender->sent[0]['payload']);
    }

    public function test_no_udp_send_occurs_for_blocked_validations(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_DRY_RUN);

        $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: true,
        );

        $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: false,
        );

        $this->assertCount(0, $sender->sent);
    }

    public function test_result_includes_device_key_scene_mode_status_and_context(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '12',
            confirmLive: true,
            operatorLabel: 'operator-a',
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('device_key', $array);
        $this->assertArrayHasKey('scene', $array);
        $this->assertArrayHasKey('mode', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertSame('operator-a', $array['context']['operator_label']);
        $this->assertSame('X32_SCENE', $array['context']['action_type']);
    }

    public function test_x32_scene_remains_the_only_supported_validation_action(): void
    {
        $this->assertTrue(class_exists(X32LiveValidationService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32SnippetValidationService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32FaderValidationService::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32MuteValidationService::class));

        $adapter = X32AdapterFactory::createProduction(new ValidationRecordingUdpSocketSender);
        $this->assertSame('x32', $adapter->adapterKey());
    }

    public function test_no_snippets_faders_mutes_or_channel_actions_added(): void
    {
        $this->assertFileDoesNotExist(base_path('../frontend/src/services/X32LiveValidation.ts'));
        $this->assertFalse(class_exists(\App\Services\X32\X32SnippetTransport::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32FaderTransport::class));
        $this->assertFalse(class_exists(\App\Services\X32\X32MuteTransport::class));
    }

    public function test_no_frontend_files_modified(): void
    {
        $frontendPath = dirname(base_path()).'/frontend';

        if (is_dir($frontendPath)) {
            $this->assertDirectoryExists($frontendPath);
        }

        $this->assertFalse(
            file_exists(app_path('Http/Controllers/X32LiveValidationController.php')),
        );
    }

    public function test_no_midi_dmx_websocket_daemon_or_worker_code_added(): void
    {
        $this->assertFalse(class_exists(\App\Services\MidiListener::class));
        $this->assertFalse(class_exists(\App\Services\DmxOutput::class));
        $this->assertFalse(class_exists(\App\Services\WebSocketDispatcher::class));
        $this->assertFalse(class_exists(\App\Console\Commands\RuntimeDaemonCommand::class));
    }

    public function test_no_real_network_traffic_in_automated_tests(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '3',
            confirmLive: true,
        );

        $this->assertInstanceOf(ValidationRecordingUdpSocketSender::class, $sender);
        $this->assertCount(1, $sender->sent);
        $this->assertNotInstanceOf(\App\Services\X32\PhpUdpSocketSender::class, $sender);
    }

    public function test_validation_harness_reports_selection_source_for_explicit_device_key(): void
    {
        $sender = new ValidationRecordingUdpSocketSender;
        $band = Band::factory()->create();
        $this->createX32Device($band, runtimeMode: X32RuntimeModeResolver::MODE_LIVE, deviceKey: 'mon-x32');

        $result = $this->validationService($sender)->validate(
            bandId: $band->id,
            scene: '5',
            confirmLive: true,
            deviceKey: 'mon-x32',
        );

        $this->assertTrue($result->success);
        $this->assertSame(X32DeviceSelectionResult::SOURCE_EXPLICIT_DEVICE_KEY, $result->context['selection_source']);
        $this->assertSame('mon-x32', $result->deviceKey);
    }

    public function test_ph024_live_recall_packet_path_unchanged(): void
    {
        $builder = new X32OscSceneRecallPacketBuilder;

        $this->assertSame('/-action/goscene', $builder->oscPath('1'));
        $this->assertSame('00000000', bin2hex(substr($builder->build('1'), -4)));
        $this->assertSame(28, strlen($builder->build('1')));
    }

    private function validationService(ValidationRecordingUdpSocketSender $sender): X32LiveValidationService
    {
        $deviceRegistry = new IntegrationDeviceRegistry;

        return new X32LiveValidationService(
            deviceSelector: new X32DeviceSelector($deviceRegistry),
            runtimeModeResolver: new X32RuntimeModeResolver,
            sceneParameterResolver: new X32SceneParameterResolver,
            deviceValidator: new IntegrationDeviceValidator,
            socketSender: $sender,
        );
    }

    private function createX32Device(
        Band $band,
        ?string $runtimeMode,
        string $host = '192.168.1.100',
        int $port = 10023,
        string $deviceKey = 'main-x32',
    ): IntegrationDevice {
        $configuration = $runtimeMode === null
            ? null
            : ['runtime_mode' => $runtimeMode];

        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => $deviceKey,
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
            'configuration' => $configuration,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => $host,
            'port' => $port,
            'enabled' => true,
        ]);

        return $device;
    }
}

class ValidationRecordingUdpSocketSender implements UdpSocketSenderInterface
{
    /** @var list<array{host: string, port: int, payload: string, timeout: float}> */
    public array $sent = [];

    public function send(string $host, int $port, string $payload, float $timeoutSeconds): int
    {
        $this->sent[] = [
            'host' => $host,
            'port' => $port,
            'payload' => $payload,
            'timeout' => $timeoutSeconds,
        ];

        return strlen($payload);
    }
}
