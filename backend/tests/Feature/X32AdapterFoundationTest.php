<?php

namespace Tests\Feature;

use App\Contracts\Runtime\RuntimeAdapterInterface;
use App\Contracts\X32\X32TransportInterface;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Performance;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use App\Models\RuntimeEvent;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\AdapterExecutionResult;
use App\Services\Runtime\AdapterRegistry;
use App\Services\Runtime\Adapters\X32Adapter;
use App\Services\Runtime\Adapters\X32AdapterFactory;
use App\Services\Runtime\RuntimeExecutionOrchestrator;
use App\Services\X32\DryRunX32Transport;
use App\Services\X32\X32DispatchContextResolver;
use App\Services\X32\X32SceneParameterResolver;
use App\Services\X32\X32SceneRecallCommand;
use App\Services\X32\X32TransportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32AdapterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_x32_adapter_implements_runtime_adapter_interface(): void
    {
        $adapter = X32AdapterFactory::createDryRun();

        $this->assertInstanceOf(RuntimeAdapterInterface::class, $adapter);
    }

    public function test_adapter_key_returns_x32(): void
    {
        $this->assertSame('x32', X32AdapterFactory::createDryRun()->adapterKey());
    }

    public function test_supports_returns_true_for_x32_scene(): void
    {
        $item = RuntimeDispatchItem::factory()->create([
            'action_type_code' => 'X32_SCENE',
        ]);

        $this->assertTrue(X32AdapterFactory::createDryRun()->supports($item));
    }

    public function test_supports_returns_false_for_non_x32_scene_action_types(): void
    {
        $item = RuntimeDispatchItem::factory()->create([
            'action_type_code' => 'LIGHT_SCENE',
            'adapter_key' => 'lighting',
        ]);

        $this->assertFalse(X32AdapterFactory::createDryRun()->supports($item));
    }

    public function test_execute_in_dry_run_mode_acknowledges_valid_scene_action(): void
    {
        [$dispatchItem] = $this->createX32DispatchScenario();

        $result = X32AdapterFactory::createDryRun()->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame(AdapterExecutionResult::STATUS_ACKNOWLEDGED, $result->status);
    }

    public function test_dry_run_does_not_open_sockets_or_call_real_hardware(): void
    {
        $transport = new RecordingX32Transport;
        $adapter = new X32Adapter(
            contextResolver: new X32DispatchContextResolver(new IntegrationDeviceRegistry),
            sceneParameterResolver: new X32SceneParameterResolver,
            transport: $transport,
            dryRun: true,
        );

        [$dispatchItem] = $this->createX32DispatchScenario();
        $adapter->execute(app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem));

        $this->assertTrue($transport->recallSceneCalled);
        $this->assertTrue($transport->lastCommand?->dryRun);
        $this->assertInstanceOf(DryRunX32Transport::class, new DryRunX32Transport);
        $this->assertFalse(class_exists(\App\Services\X32\SocketX32Transport::class));
    }

    public function test_missing_scene_parameter_returns_failed_result(): void
    {
        [$dispatchItem] = $this->createX32DispatchScenario(scene: null);
        $adapter = X32AdapterFactory::createDryRun();

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
        $this->assertSame(AdapterExecutionResult::STATUS_FAILED, $result->status);
    }

    public function test_invalid_scene_parameter_returns_failed_result(): void
    {
        [$dispatchItem] = $this->createX32DispatchScenario(scene: 'abc');
        $adapter = X32AdapterFactory::createDryRun();

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
        $this->assertSame(AdapterExecutionResult::STATUS_FAILED, $result->status);
    }

    public function test_missing_enabled_x32_device_returns_failed_result(): void
    {
        [$dispatchItem] = $this->createX32DispatchScenario(createDevice: false);
        $adapter = X32AdapterFactory::createDryRun();

        $result = $adapter->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No enabled X32 integration device', $result->message ?? '');
    }

    public function test_disabled_x32_device_is_not_used(): void
    {
        [$dispatchItem, $performance] = $this->createX32DispatchScenario(createDevice: false);

        IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => 'main-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => false,
        ]);

        $registry = new IntegrationDeviceRegistry;
        $this->assertNull($registry->resolve($performance->band_id, IntegrationDevice::TYPE_X32));

        $result = X32AdapterFactory::createDryRun()->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertFalse($result->success);
    }

    public function test_enabled_x32_integration_device_and_profile_resolve_through_registry(): void
    {
        [$dispatchItem, $performance, $device] = $this->createX32DispatchScenario();

        $resolved = (new IntegrationDeviceRegistry)->resolve(
            $performance->band_id,
            IntegrationDevice::TYPE_X32,
            $device->device_key,
        );

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($device));
        $this->assertNotNull($resolved->integrationConnectionProfiles->first());
    }

    public function test_result_context_includes_mode_dry_run_and_scene(): void
    {
        [$dispatchItem] = $this->createX32DispatchScenario(scene: '5');

        $result = X32AdapterFactory::createDryRun()->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertSame('dry_run', $result->context['mode']);
        $this->assertSame('5', $result->context['scene']);
        $this->assertSame('x32', $result->context['adapter']);
        $this->assertSame('main-x32', $result->context['device_key']);
        $this->assertSame('primary', $result->context['profile_name']);
    }

    public function test_runtime_execution_orchestrator_processes_x32_scene_dispatch_item_using_x32_adapter(): void
    {
        [$dispatchItem, , , $dispatch] = $this->createX32DispatchScenario(returnDispatch: true);

        $adapterRegistry = new AdapterRegistry;
        $adapterRegistry->register(X32AdapterFactory::createDryRun());

        $summary = (new RuntimeExecutionOrchestrator(
            app(AdapterExecutionRequestFactory::class),
            $adapterRegistry,
        ))->execute($dispatch);

        $this->assertSame(RuntimeDispatch::STATUS_COMPLETED, $summary->status);
        $this->assertSame(
            RuntimeDispatchItem::STATUS_ACKNOWLEDGED,
            $dispatchItem->fresh()->status,
        );
    }

    public function test_no_midi_dmx_websocket_daemon_or_worker_code_added(): void
    {
        $this->assertFalse(class_exists(\App\Services\MidiListener::class));
        $this->assertFalse(class_exists(\App\Services\DmxOutput::class));
        $this->assertFalse(class_exists(\App\Services\WebSocketDispatcher::class));

        $adapterMethods = get_class_methods(X32Adapter::class);
        $this->assertNotContains('connect', $adapterMethods);
        $this->assertNotContains('sendOsc', $adapterMethods);
    }

    public function test_only_x32_scene_is_supported_by_x32_adapter(): void
    {
        $adapter = X32AdapterFactory::createDryRun();

        $unsupportedTypes = ['X32_SNIPPET', 'X32_MUTE', 'X32_FADER', 'LIGHT_SCENE', 'VIDEO_CUE'];

        foreach ($unsupportedTypes as $type) {
            $item = RuntimeDispatchItem::factory()->create([
                'action_type_code' => $type,
            ]);

            $this->assertFalse($adapter->supports($item), "Expected unsupported type: {$type}");
        }
    }

    /**
     * @return array{0: RuntimeDispatchItem, 1: Performance, 2?: IntegrationDevice, 3?: RuntimeDispatch}
     */
    private function createX32DispatchScenario(
        ?string $scene = '5',
        bool $createDevice = true,
        bool $returnDispatch = false,
    ): array {
        $performance = Performance::factory()->create();

        if ($createDevice) {
            $device = IntegrationDevice::factory()->forBand($performance->band)->create([
                'device_key' => 'main-x32',
                'device_type' => IntegrationDevice::TYPE_X32,
                'enabled' => true,
            ]);

            IntegrationConnectionProfile::factory()->create([
                'integration_device_id' => $device->id,
                'profile_name' => 'primary',
                'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
                'host' => '192.168.1.100',
                'port' => 10023,
                'enabled' => true,
            ]);
        } else {
            $device = null;
        }

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

        $payload = [
            'action_type_code' => 'X32_SCENE',
            'action_definition_code' => 'RECALL_SCENE',
            'action_definition_name' => 'Recall Scene',
            'parameters' => $scene === null ? [] : ['scene' => $scene],
        ];

        $dispatchItem = RuntimeDispatchItem::factory()->create([
            'runtime_dispatch_id' => $dispatch->id,
            'runtime_action_item_id' => $actionItem->id,
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
            'payload' => $payload,
            'status' => RuntimeDispatchItem::STATUS_READY,
            'attempts' => 0,
        ]);

        $result = [
            $dispatchItem->fresh(['runtimeDispatch.performance']),
            $performance,
        ];

        if ($device !== null) {
            $result[] = $device->fresh('integrationConnectionProfiles');
        }

        if ($returnDispatch) {
            $result[] = $dispatch->fresh(['runtimeDispatchItems', 'runtimeActionPlan.runtimeEvent']);
        }

        return $result;
    }
}

class RecordingX32Transport implements X32TransportInterface
{
    public bool $recallSceneCalled = false;

    public ?X32SceneRecallCommand $lastCommand = null;

    public function recallScene(X32SceneRecallCommand $command): X32TransportResult
    {
        $this->recallSceneCalled = true;
        $this->lastCommand = $command;

        return (new DryRunX32Transport)->recallScene($command);
    }
}
