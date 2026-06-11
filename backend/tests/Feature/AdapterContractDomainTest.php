<?php

namespace Tests\Feature;

use App\Contracts\Runtime\RuntimeAdapterInterface;
use App\Exceptions\Runtime\AdapterNotFoundException;
use App\Models\RuntimeDispatchItem;
use App\Services\Runtime\AdapterExecutionRequest;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\AdapterExecutionResult;
use App\Services\Runtime\AdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdapterContractDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_execution_request_can_be_created_from_runtime_dispatch_item(): void
    {
        $item = $this->createDispatchItem();
        $factory = app(AdapterExecutionRequestFactory::class);

        $request = $factory->fromDispatchItem($item);

        $this->assertInstanceOf(AdapterExecutionRequest::class, $request);
        $this->assertSame($item->id, $request->runtimeDispatchItemId);
    }

    public function test_request_preserves_adapter_key_action_type_code_payload_and_attempt_number(): void
    {
        $item = RuntimeDispatchItem::factory()->create([
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
            'payload' => [
                'action_type_code' => 'X32_SCENE',
                'action_definition_code' => 'RECALL_INTRO_SCENE',
                'action_definition_name' => 'Recall Intro Scene',
                'parameters' => ['scene' => '05'],
            ],
            'attempts' => 2,
        ]);

        $request = app(AdapterExecutionRequestFactory::class)->fromDispatchItem($item);

        $this->assertSame('x32', $request->adapterKey);
        $this->assertSame('X32_SCENE', $request->actionTypeCode);
        $this->assertSame(['scene' => '05'], $request->payload['parameters']);
        $this->assertSame(3, $request->attemptNumber);
    }

    public function test_adapter_execution_result_supports_success_result(): void
    {
        $result = AdapterExecutionResult::acknowledged(
            adapterKey: 'x32',
            message: 'Prepared for future execution.',
            context: ['dispatch_item_id' => 1],
        );

        $this->assertTrue($result->success);
        $this->assertSame(AdapterExecutionResult::STATUS_ACKNOWLEDGED, $result->status);
        $this->assertSame('x32', $result->adapterKey);
        $this->assertSame('Prepared for future execution.', $result->message);
    }

    public function test_adapter_execution_result_supports_failure_result(): void
    {
        $result = AdapterExecutionResult::failed(
            adapterKey: 'lighting',
            message: 'Adapter reported failure.',
            context: ['reason' => 'not_implemented'],
        );

        $this->assertFalse($result->success);
        $this->assertSame(AdapterExecutionResult::STATUS_FAILED, $result->status);
        $this->assertSame('lighting', $result->adapterKey);
    }

    public function test_adapter_registry_registers_and_resolves_adapter_by_key(): void
    {
        $registry = new AdapterRegistry;
        $adapter = new FakeRuntimeAdapter('x32', supportsAll: true);

        $registry->register($adapter);

        $this->assertTrue($registry->has('x32'));
        $this->assertSame($adapter, $registry->resolve('x32'));
        $this->assertSame(['x32'], $registry->registeredKeys());
    }

    public function test_adapter_registry_reports_missing_adapter_cleanly(): void
    {
        $registry = new AdapterRegistry;

        $this->expectException(AdapterNotFoundException::class);
        $this->expectExceptionMessage('No runtime adapter registered for key [missing].');

        $registry->resolve('missing');
    }

    public function test_adapter_registry_does_not_resolve_unregistered_adapter_keys_silently(): void
    {
        $registry = new AdapterRegistry;
        $registry->register(new FakeRuntimeAdapter('x32', supportsAll: true));

        $this->assertFalse($registry->has('lighting'));

        try {
            $registry->resolve('lighting');
            $this->fail('Expected AdapterNotFoundException was not thrown.');
        } catch (AdapterNotFoundException $exception) {
            $this->assertStringContainsString('lighting', $exception->getMessage());
        }
    }

    public function test_runtime_adapter_interface_support_check_can_reject_unsupported_item(): void
    {
        $item = RuntimeDispatchItem::factory()->create([
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
        ]);

        $adapter = new FakeRuntimeAdapter('x32', supportsAll: false);

        $this->assertFalse($adapter->supports($item));
    }

    public function test_no_runtime_dispatch_item_status_changes_occur_when_creating_request(): void
    {
        $item = $this->createDispatchItem();
        $originalStatus = $item->status;
        $originalAttempts = $item->attempts;

        app(AdapterExecutionRequestFactory::class)->fromDispatchItem($item);

        $fresh = $item->fresh();
        $this->assertSame($originalStatus, $fresh->status);
        $this->assertSame($originalAttempts, $fresh->attempts);
    }

    public function test_no_execution_adapters_or_hardware_calls_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32Adapter::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\MusicianDeviceAdapter::class));
        $this->assertFalse(class_exists(\App\Services\VideoAdapter::class));
        $this->assertFalse(class_exists(\App\Services\ExecutionDispatcher::class));

        $registryMethods = get_class_methods(AdapterRegistry::class);
        $this->assertNotContains('dispatch', $registryMethods);
        $this->assertNotContains('send', $registryMethods);
    }

    private function createDispatchItem(): RuntimeDispatchItem
    {
        return RuntimeDispatchItem::factory()->create([
            'adapter_key' => 'x32',
            'action_type_code' => 'X32_SCENE',
            'payload' => [
                'action_type_code' => 'X32_SCENE',
                'action_definition_code' => 'RECALL_INTRO_SCENE',
                'action_definition_name' => 'Recall Intro Scene',
                'parameters' => ['scene' => '05'],
            ],
            'attempts' => 0,
            'status' => RuntimeDispatchItem::STATUS_READY,
        ]);
    }
}

class FakeRuntimeAdapter implements RuntimeAdapterInterface
{
    public function __construct(
        private readonly string $key,
        private readonly bool $supportsAll,
    ) {}

    public function adapterKey(): string
    {
        return $this->key;
    }

    public function supports(RuntimeDispatchItem $item): bool
    {
        return $this->supportsAll && $item->adapter_key === $this->key;
    }

    public function execute(AdapterExecutionRequest $request): AdapterExecutionResult
    {
        return AdapterExecutionResult::acknowledged(
            adapterKey: $this->key,
            message: 'Fake adapter contract response.',
            context: ['runtime_dispatch_item_id' => $request->runtimeDispatchItemId],
        );
    }
}
