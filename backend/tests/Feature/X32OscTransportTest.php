<?php

namespace Tests\Feature;

use App\Contracts\X32\UdpSocketClientInterface;
use App\Contracts\X32\X32TransportInterface;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Performance;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use App\Models\RuntimeEvent;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\Adapters\X32Adapter;
use App\Services\Runtime\Adapters\X32AdapterFactory;
use App\Services\X32\FakeUdpSocketClient;
use App\Services\X32\OscX32Transport;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32SceneRecallCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32OscTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_osc_x32_transport_implements_x32_transport_interface(): void
    {
        $transport = $this->createOscTransport();

        $this->assertInstanceOf(X32TransportInterface::class, $transport);
    }

    public function test_osc_packet_builder_creates_deterministic_packet_bytes_for_scene_recall(): void
    {
        $builder = new X32OscSceneRecallPacketBuilder;

        $this->assertSame('/3/scene/05', $builder->oscPath('5'));

        $packet = $builder->build('5');
        $expected = "/3/scene/05\0,\0\0\0";

        $this->assertSame($expected, $packet);
        $this->assertSame(16, strlen($packet));
    }

    public function test_live_osc_transport_uses_host_and_port_from_connection_profile(): void
    {
        $socket = new FakeUdpSocketClient;
        $transport = new OscX32Transport(
            packetBuilder: new X32OscSceneRecallPacketBuilder,
            socketClient: $socket,
            liveSendingEnabled: true,
        );

        $result = $transport->recallScene(new X32SceneRecallCommand(
            scene: '12',
            deviceKey: 'main-x32',
            profileName: 'primary',
            protocol: IntegrationConnectionProfile::PROTOCOL_OSC,
            host: '192.168.1.77',
            port: 10023,
            dryRun: false,
        ));

        $this->assertTrue($result->success);
        $this->assertSame('192.168.1.77', $socket->sent[0]['host']);
        $this->assertSame(10023, $socket->sent[0]['port']);
        $this->assertSame('osc', $result->mode);
        $this->assertSame('/3/scene/12', $result->context['osc_path']);
    }

    public function test_live_osc_transport_sends_through_socket_interface_when_explicitly_enabled(): void
    {
        $socket = new FakeUdpSocketClient;
        $adapter = X32AdapterFactory::createLiveOsc($socket);
        [$dispatchItem] = $this->createOscDispatchScenario(host: '10.0.0.5', port: 10023);

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertCount(1, $socket->sent);
        $this->assertSame('10.0.0.5', $socket->sent[0]['host']);
        $this->assertGreaterThan(0, $result->context['bytes_sent']);
    }

    public function test_default_osc_factory_mode_does_not_send_real_network_traffic(): void
    {
        $socket = new FakeUdpSocketClient;
        $adapter = X32AdapterFactory::createOscTransport(
            liveSendingEnabled: false,
            socketClient: $socket,
        );
        [$dispatchItem] = $this->createOscDispatchScenario();

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('osc_safe', $result->context['mode']);
        $this->assertCount(0, $socket->sent);
        $this->assertSame(0, $result->context['bytes_sent']);
    }

    public function test_socket_failure_returns_failed_x32_transport_result(): void
    {
        $socket = new FakeUdpSocketClient;
        $socket->shouldFail = true;

        $result = (new OscX32Transport(
            packetBuilder: new X32OscSceneRecallPacketBuilder,
            socketClient: $socket,
            liveSendingEnabled: true,
        ))->recallScene(new X32SceneRecallCommand(
            scene: '3',
            deviceKey: 'main-x32',
            profileName: 'primary',
            protocol: IntegrationConnectionProfile::PROTOCOL_OSC,
            host: '192.168.1.50',
            port: 10023,
            dryRun: false,
        ));

        $this->assertFalse($result->success);
        $this->assertSame('osc', $result->mode);
        $this->assertStringContainsString('UDP socket send failed', $result->message ?? '');
    }

    public function test_x32_adapter_can_use_osc_x32_transport_for_x32_scene(): void
    {
        $adapter = X32AdapterFactory::createOscTransport();
        [$dispatchItem] = $this->createOscDispatchScenario(scene: '7');

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('7', $result->context['scene']);
        $this->assertSame('/3/scene/07', $result->context['osc_path']);
    }

    public function test_only_x32_scene_remains_supported(): void
    {
        $adapter = X32AdapterFactory::createOscTransport();

        $unsupported = RuntimeDispatchItem::factory()->create([
            'action_type_code' => 'X32_SNIPPET',
        ]);

        $this->assertFalse($adapter->supports($unsupported));
    }

    public function test_no_snippets_faders_mutes_or_channel_actions_added(): void
    {
        $adapter = X32AdapterFactory::createOscTransport();
        $blockedTypes = ['X32_SNIPPET', 'X32_MUTE', 'X32_FADER', 'LIGHT_SCENE', 'VIDEO_CUE'];

        foreach ($blockedTypes as $type) {
            $item = RuntimeDispatchItem::factory()->create(['action_type_code' => $type]);
            $this->assertFalse($adapter->supports($item), "Unexpected support for {$type}");
        }
    }

    public function test_no_midi_dmx_websocket_daemon_or_worker_code_added(): void
    {
        $this->assertFalse(class_exists(\App\Services\MidiListener::class));
        $this->assertFalse(class_exists(\App\Services\DmxOutput::class));
        $this->assertFalse(class_exists(\App\Services\WebSocketDispatcher::class));

        $transportMethods = get_class_methods(OscX32Transport::class);
        $this->assertNotContains('listen', $transportMethods);
        $this->assertNotContains('worker', $transportMethods);
    }

    public function test_no_real_socket_calls_occur_in_tests(): void
    {
        $this->assertInstanceOf(UdpSocketClientInterface::class, new FakeUdpSocketClient);
        $this->assertFalse(class_exists(\App\Services\X32\PhpUdpSocketClient::class));
    }

    private function createOscTransport(bool $live = false): OscX32Transport
    {
        return new OscX32Transport(
            packetBuilder: new X32OscSceneRecallPacketBuilder,
            socketClient: new FakeUdpSocketClient,
            liveSendingEnabled: $live,
        );
    }

    /**
     * @return array{0: RuntimeDispatchItem}
     */
    private function createOscDispatchScenario(
        ?string $scene = '5',
        string $host = '192.168.1.100',
        int $port = 10023,
    ): array {
        $performance = Performance::factory()->create();

        $device = IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'primary',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => $host,
            'port' => $port,
            'enabled' => true,
        ]);

        $event = RuntimeEvent::factory()->forPerformance($performance)->create([
            'status' => 'planned',
        ]);

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
                'action_type_code' => 'X32_SCENE',
                'action_definition_code' => 'RECALL_SCENE',
                'action_definition_name' => 'Recall Scene',
                'parameters' => ['scene' => $scene],
            ],
            'status' => RuntimeDispatchItem::STATUS_READY,
            'attempts' => 0,
        ]);

        return [$dispatchItem->fresh(['runtimeDispatch.performance'])];
    }
}
