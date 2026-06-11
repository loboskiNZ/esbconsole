<?php

namespace Tests\Feature;

use App\Contracts\X32\UdpSocketClientInterface;
use App\Contracts\X32\UdpSocketSenderInterface;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Performance;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use App\Models\RuntimeEvent;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\Adapters\X32AdapterFactory;
use App\Services\X32\FakeUdpSocketClient;
use App\Services\X32\OscX32Transport;
use App\Services\X32\ProductionUdpSocketClient;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32RuntimeModeResolver;
use App\Services\X32\X32SceneRecallCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32LiveUdpTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_udp_socket_client_implements_udp_socket_client_interface(): void
    {
        $client = new ProductionUdpSocketClient(new RecordingUdpSocketSender);

        $this->assertInstanceOf(UdpSocketClientInterface::class, $client);
    }

    public function test_missing_runtime_mode_defaults_to_dry_run(): void
    {
        $resolver = new X32RuntimeModeResolver;

        $this->assertSame(
            X32RuntimeModeResolver::MODE_DRY_RUN,
            $resolver->resolve(null),
        );
        $this->assertSame(
            X32RuntimeModeResolver::MODE_DRY_RUN,
            $resolver->resolve([]),
        );
    }

    public function test_runtime_mode_dry_run_does_not_send_udp(): void
    {
        $sender = new RecordingUdpSocketSender;
        $adapter = X32AdapterFactory::createProduction($sender);
        [$dispatchItem] = $this->createScenario(runtimeMode: X32RuntimeModeResolver::MODE_DRY_RUN);

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('dry_run', $result->context['mode']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_runtime_mode_disabled_does_not_send_udp(): void
    {
        $sender = new RecordingUdpSocketSender;
        $adapter = X32AdapterFactory::createProduction($sender);
        [$dispatchItem] = $this->createScenario(runtimeMode: X32RuntimeModeResolver::MODE_DISABLED);

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
        $this->assertSame('disabled', $result->context['mode']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_runtime_mode_live_allows_udp_send_through_injected_socket_boundary(): void
    {
        $sender = new RecordingUdpSocketSender;
        $adapter = X32AdapterFactory::createProduction($sender);
        [$dispatchItem] = $this->createScenario(
            runtimeMode: X32RuntimeModeResolver::MODE_LIVE,
            host: '192.168.10.20',
            port: 10023,
        );

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('live', $result->context['mode']);
        $this->assertCount(1, $sender->sent);
        $this->assertSame('192.168.10.20', $sender->sent[0]['host']);
        $this->assertGreaterThan(0, $result->context['bytes_sent']);
    }

    public function test_invalid_runtime_mode_fails_closed(): void
    {
        $resolver = new X32RuntimeModeResolver;

        $this->assertSame(
            X32RuntimeModeResolver::MODE_DISABLED,
            $resolver->resolve(['runtime_mode' => 'unsafe_live']),
        );

        $sender = new RecordingUdpSocketSender;
        $adapter = X32AdapterFactory::createProduction($sender);
        [$dispatchItem] = $this->createScenario(runtimeMode: 'unsafe_live');

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
        $this->assertCount(0, $sender->sent);
    }

    public function test_x32_adapter_factory_create_dry_run_remains_safe_default(): void
    {
        $adapter = X32AdapterFactory::createDryRun();
        [$dispatchItem] = $this->createScenario(runtimeMode: X32RuntimeModeResolver::MODE_LIVE);

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('dry_run', $result->context['mode']);
    }

    public function test_live_factory_requires_explicit_live_mode_on_device(): void
    {
        $sender = new RecordingUdpSocketSender;
        $adapter = X32AdapterFactory::createProduction($sender);
        [$dispatchItem] = $this->createScenario();

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('dry_run', $result->context['mode']);
        $this->assertCount(0, $sender->sent);
    }

    public function test_x32_scene_remains_the_only_supported_action(): void
    {
        $adapter = X32AdapterFactory::createProduction(new RecordingUdpSocketSender);

        foreach (['X32_SNIPPET', 'X32_MUTE', 'X32_FADER'] as $type) {
            $item = RuntimeDispatchItem::factory()->create(['action_type_code' => $type]);
            $this->assertFalse($adapter->supports($item));
        }
    }

    public function test_no_midi_dmx_websocket_daemon_or_worker_code_added(): void
    {
        $this->assertFalse(class_exists(\App\Services\MidiListener::class));
        $this->assertFalse(class_exists(\App\Services\DmxOutput::class));
        $this->assertFalse(class_exists(\App\Services\WebSocketDispatcher::class));
    }

    public function test_no_real_network_traffic_in_automated_tests(): void
    {
        $sender = new RecordingUdpSocketSender;
        $transport = new OscX32Transport(
            packetBuilder: new X32OscSceneRecallPacketBuilder,
            socketClient: new ProductionUdpSocketClient($sender),
            liveSendingEnabled: true,
        );

        $transport->recallScene(new X32SceneRecallCommand(
            scene: '4',
            deviceKey: 'main-x32',
            profileName: 'primary',
            protocol: IntegrationConnectionProfile::PROTOCOL_OSC,
            host: '127.0.0.1',
            port: 10023,
            dryRun: true,
            runtimeMode: X32RuntimeModeResolver::MODE_DRY_RUN,
        ));

        $this->assertCount(0, $sender->sent);
        $this->assertInstanceOf(RecordingUdpSocketSender::class, $sender);
    }

    /**
     * @return array{0: RuntimeDispatchItem}
     */
    private function createScenario(
        ?string $runtimeMode = null,
        string $host = '192.168.1.100',
        int $port = 10023,
    ): array {
        $performance = Performance::factory()->create();

        $configuration = $runtimeMode === null
            ? null
            : ['runtime_mode' => $runtimeMode];

        $device = IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => 'main-x32',
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

        $event = RuntimeEvent::factory()->forPerformance($performance)->create(['status' => 'planned']);
        $plan = RuntimeActionPlan::factory()->create([
            'runtime_event_id' => $event->id,
            'performance_id' => $performance->id,
            'status' => RuntimeActionPlan::STATUS_READY,
        ]);
        $dispatch = RuntimeDispatch::factory()->create([
            'runtime_action_plan_id' => $plan->id,
            'performance_id' => $performance->id,
            'status' => RuntimeDispatch::STATUS_READY,
        ]);
        $actionItem = RuntimeActionItem::factory()->create([
            'runtime_action_plan_id' => $plan->id,
            'action_type_code' => 'X32_SCENE',
        ]);
        $dispatchItem = RuntimeDispatchItem::factory()->create([
            'runtime_dispatch_id' => $dispatch->id,
            'runtime_action_item_id' => $actionItem->id,
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
            'payload' => [
                'parameters' => ['scene' => '5'],
            ],
            'status' => RuntimeDispatchItem::STATUS_READY,
        ]);

        return [$dispatchItem->fresh(['runtimeDispatch.performance'])];
    }
}

class RecordingUdpSocketSender implements UdpSocketSenderInterface
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
