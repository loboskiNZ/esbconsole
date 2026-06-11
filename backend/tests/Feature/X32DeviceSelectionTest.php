<?php

namespace Tests\Feature;

use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Performance;
use App\Models\PerformanceDeviceAssignment;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use App\Models\RuntimeEvent;
use App\Services\Integration\IntegrationDeviceRegistry;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\Adapters\X32AdapterFactory;
use App\Services\X32\X32DeviceSelector;
use App\Services\X32\X32DeviceSelectionResult;
use App\Services\X32\X32DispatchContextResolver;
use App\Services\X32\X32OscSceneRecallPacketBuilder;
use App\Services\X32\X32RuntimeModeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class X32DeviceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_assignment_selects_foh_device(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($monDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_MONITORS]);

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($fohDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_FOH]);

        $selection = $this->deviceSelector()->select(
            bandId: $performance->band_id,
            performanceId: $performance->id,
        );

        $this->assertNotNull($selection);
        $this->assertTrue($selection->device->is($fohDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_PERFORMANCE_ASSIGNMENT, $selection->selectionSource);
    }

    public function test_explicit_device_key_selects_matching_device(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        $selection = $this->deviceSelector()->select(
            bandId: $performance->band_id,
            deviceKey: 'mon-x32',
        );

        $this->assertNotNull($selection);
        $this->assertTrue($selection->device->is($monDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_EXPLICIT_DEVICE_KEY, $selection->selectionSource);
    }

    public function test_fallback_still_selects_first_enabled_x32_device(): void
    {
        [$performance, $fohDevice] = $this->createMultiConsoleBand(createMon: false);

        $selection = $this->deviceSelector()->select(
            bandId: $performance->band_id,
        );

        $this->assertNotNull($selection);
        $this->assertTrue($selection->device->is($fohDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_BAND_FALLBACK, $selection->selectionSource);
    }

    public function test_multiple_enabled_x32_devices_are_supported_simultaneously(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => 'backup-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        $enabled = IntegrationDevice::query()
            ->where('band_id', $performance->band_id)
            ->where('device_type', IntegrationDevice::TYPE_X32)
            ->where('enabled', true)
            ->count();

        $this->assertSame(3, $enabled);
        $this->assertNotSame($fohDevice->id, $monDevice->id);
    }

    public function test_wrong_device_key_fails_cleanly(): void
    {
        [$performance] = $this->createMultiConsoleBand();

        $selection = $this->deviceSelector()->select(
            bandId: $performance->band_id,
            deviceKey: 'missing-x32',
        );

        $this->assertNull($selection);
    }

    public function test_disabled_device_is_not_selected(): void
    {
        $performance = Performance::factory()->create();

        IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => 'disabled-x32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => false,
        ]);

        $this->assertNull($this->deviceSelector()->select(
            bandId: $performance->band_id,
            deviceKey: 'disabled-x32',
        ));
    }

    public function test_performance_assignment_preferred_over_fallback(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($monDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_MONITORS]);

        $dispatchItem = $this->createDispatchItem($performance, deviceKey: null);
        $context = $this->contextResolver()->resolve($dispatchItem->id);

        $this->assertNotNull($context);
        $this->assertTrue($context->device->is($monDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_PERFORMANCE_ASSIGNMENT, $context->selectionSource);
        $this->assertNotSame($fohDevice->id, $context->device->id);
    }

    public function test_performance_assignment_preferred_over_explicit_device_key(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($fohDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_FOH]);

        $dispatchItem = $this->createDispatchItem($performance, deviceKey: 'mon-x32');
        $context = $this->contextResolver()->resolve($dispatchItem->id);

        $this->assertNotNull($context);
        $this->assertTrue($context->device->is($fohDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_PERFORMANCE_ASSIGNMENT, $context->selectionSource);
        $this->assertNotSame($monDevice->id, $context->device->id);
    }

    public function test_dispatch_resolver_uses_explicit_device_key_when_no_assignment(): void
    {
        [$performance, , $monDevice] = $this->createMultiConsoleBand();

        $dispatchItem = $this->createDispatchItem($performance, deviceKey: 'mon-x32');
        $context = $this->contextResolver()->resolve($dispatchItem->id);

        $this->assertNotNull($context);
        $this->assertTrue($context->device->is($monDevice));
        $this->assertSame(X32DeviceSelectionResult::SOURCE_EXPLICIT_DEVICE_KEY, $context->selectionSource);
    }

    public function test_ph024_live_recall_packet_format_unchanged(): void
    {
        $builder = new X32OscSceneRecallPacketBuilder;

        $packet = $builder->build('5');

        $this->assertSame('/-action/goscene', $builder->oscPath('5'));
        $this->assertSame('00000005', bin2hex(substr($packet, -4)));
        $this->assertSame(28, strlen($packet));
        $this->assertStringNotContainsString('/3/scene', $packet);
    }

    public function test_no_osc_packet_or_transport_changes_beyond_device_selection(): void
    {
        $this->assertSame(
            '/-action/goscene',
            (new X32OscSceneRecallPacketBuilder)->oscPath('1'),
        );
        $this->assertFalse(class_exists(\App\Services\X32\X32SnippetTransport::class));
        $this->assertFalse(class_exists(\App\Services\MidiListener::class));
        $this->assertFalse(class_exists(\App\Services\DmxOutput::class));
        $this->assertFalse(class_exists(\App\Services\WebSocketDispatcher::class));
    }

    public function test_performance_relationships_expose_assigned_integration_devices(): void
    {
        [$performance, $fohDevice] = $this->createMultiConsoleBand(createMon: false);

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($fohDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_FOH]);

        $performance->load('assignedIntegrationDevices', 'performanceDeviceAssignments');

        $this->assertCount(1, $performance->performanceDeviceAssignments);
        $this->assertCount(1, $performance->assignedIntegrationDevices);
        $this->assertTrue($performance->assignedIntegrationDevices->first()->is($fohDevice));
        $this->assertCount(1, $fohDevice->fresh()->performanceDeviceAssignments);
    }

    public function test_adapter_execute_uses_performance_assigned_device(): void
    {
        [$performance, $fohDevice, $monDevice] = $this->createMultiConsoleBand();

        PerformanceDeviceAssignment::factory()
            ->forPerformance($performance)
            ->forDevice($monDevice)
            ->create(['role' => PerformanceDeviceAssignment::ROLE_MONITORS]);

        $dispatchItem = $this->createDispatchItem($performance);
        $result = X32AdapterFactory::createDryRun()->execute(
            app(AdapterExecutionRequestFactory::class)->fromDispatchItem($dispatchItem),
        );

        $this->assertTrue($result->success);
        $this->assertSame('mon-x32', $result->context['device_key']);
        $this->assertNotSame($fohDevice->device_key, $result->context['device_key']);
    }

    /**
     * @return array{0: Performance, 1: IntegrationDevice, 2?: IntegrationDevice}
     */
    private function createMultiConsoleBand(bool $createMon = true): array
    {
        $performance = Performance::factory()->create();

        $fohDevice = $this->createX32Device($performance, 'foh-x32');
        $monDevice = $createMon ? $this->createX32Device($performance, 'mon-x32') : null;

        return $createMon
            ? [$performance, $fohDevice, $monDevice]
            : [$performance, $fohDevice];
    }

    private function createX32Device(Performance $performance, string $deviceKey): IntegrationDevice
    {
        $device = IntegrationDevice::factory()->forBand($performance->band)->create([
            'device_key' => $deviceKey,
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

        return $device;
    }

    private function createDispatchItem(Performance $performance, ?string $deviceKey = 'foh-x32'): RuntimeDispatchItem
    {
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

        $payload = [
            'parameters' => ['scene' => '5'],
        ];

        if ($deviceKey !== null) {
            $payload['device_key'] = $deviceKey;
        }

        return RuntimeDispatchItem::factory()->create([
            'runtime_dispatch_id' => $dispatch->id,
            'runtime_action_item_id' => $actionItem->id,
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
            'payload' => $payload,
            'status' => RuntimeDispatchItem::STATUS_READY,
        ])->fresh(['runtimeDispatch.performance']);
    }

    private function deviceSelector(): X32DeviceSelector
    {
        return new X32DeviceSelector(new IntegrationDeviceRegistry);
    }

    private function contextResolver(): X32DispatchContextResolver
    {
        return new X32DispatchContextResolver($this->deviceSelector());
    }
}
