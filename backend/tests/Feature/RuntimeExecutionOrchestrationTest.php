<?php

namespace Tests\Feature;

use App\Contracts\Runtime\RuntimeAdapterInterface;
use App\Models\RuntimeActionItem;
use App\Models\RuntimeActionPlan;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use App\Models\RuntimeEvent;
use App\Services\Runtime\AdapterExecutionRequest;
use App\Services\Runtime\AdapterExecutionRequestFactory;
use App\Services\Runtime\AdapterExecutionResult;
use App\Services\Runtime\AdapterRegistry;
use App\Services\Runtime\RuntimeExecutionOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RuntimeExecutionOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OrchestrationFakeAdapter::$processedItemIds = [];
    }

    public function test_orchestrator_processes_ready_runtime_dispatch(): void
    {
        $dispatch = $this->createReadyDispatch();
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $summary = $orchestrator->execute($dispatch);

        $this->assertSame(RuntimeDispatch::STATUS_COMPLETED, $summary->status);
        $this->assertSame(1, $summary->itemCount);
        $this->assertSame(RuntimeDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);
    }

    public function test_items_are_processed_in_sort_order(): void
    {
        $dispatch = $this->createReadyDispatch(itemCount: 2, sortOrders: [20, 10]);
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $orchestrator->execute($dispatch);

        $items = $dispatch->runtimeDispatchItems->sortBy('sort_order')->values();
        $this->assertSame(
            [$items[0]->id, $items[1]->id],
            OrchestrationFakeAdapter::$processedItemIds,
        );
    }

    public function test_acknowledged_adapter_result_marks_item_acknowledged(): void
    {
        $dispatch = $this->createReadyDispatch();
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $orchestrator->execute($dispatch);

        $this->assertSame(
            RuntimeDispatchItem::STATUS_ACKNOWLEDGED,
            $dispatch->runtimeDispatchItems->first()->fresh()->status,
        );
    }

    public function test_failed_adapter_result_marks_item_failed_and_stores_last_error(): void
    {
        $dispatch = $this->createReadyDispatch();
        $adapter = new OrchestrationFakeAdapter(
            key: 'x32',
            supportsItems: true,
            result: AdapterExecutionResult::failed('x32', 'Adapter reported failure.'),
        );
        $orchestrator = $this->createOrchestrator($adapter);

        $orchestrator->execute($dispatch);

        $item = $dispatch->runtimeDispatchItems->first()->fresh();
        $this->assertSame(RuntimeDispatchItem::STATUS_FAILED, $item->status);
        $this->assertSame('Adapter reported failure.', $item->last_error);
    }

    public function test_skipped_adapter_result_marks_item_skipped(): void
    {
        $dispatch = $this->createReadyDispatch();
        $adapter = new OrchestrationFakeAdapter(
            key: 'x32',
            supportsItems: true,
            result: new AdapterExecutionResult(
                adapterKey: 'x32',
                success: false,
                status: AdapterExecutionResult::STATUS_SKIPPED,
                message: 'Adapter skipped item.',
                context: [],
                occurredAt: now(),
            ),
        );
        $orchestrator = $this->createOrchestrator($adapter);

        $orchestrator->execute($dispatch);

        $this->assertSame(
            RuntimeDispatchItem::STATUS_SKIPPED,
            $dispatch->runtimeDispatchItems->first()->fresh()->status,
        );
    }

    public function test_unsupported_adapter_result_marks_item_skipped(): void
    {
        $dispatch = $this->createReadyDispatch();
        $adapter = new OrchestrationFakeAdapter(
            key: 'x32',
            supportsItems: true,
            result: new AdapterExecutionResult(
                adapterKey: 'x32',
                success: false,
                status: AdapterExecutionResult::STATUS_UNSUPPORTED,
                message: 'Adapter does not support action.',
                context: [],
                occurredAt: now(),
            ),
        );
        $orchestrator = $this->createOrchestrator($adapter);

        $orchestrator->execute($dispatch);

        $item = $dispatch->runtimeDispatchItems->first()->fresh();
        $this->assertSame(RuntimeDispatchItem::STATUS_SKIPPED, $item->status);
        $this->assertSame('Adapter does not support action.', $item->last_error);
    }

    public function test_missing_adapter_marks_item_failed_and_creates_audit_record(): void
    {
        $dispatch = $this->createReadyDispatch(adapterKey: 'missing');
        $orchestrator = $this->createOrchestrator();

        $orchestrator->execute($dispatch);

        $item = $dispatch->runtimeDispatchItems->first()->fresh();
        $this->assertSame(RuntimeDispatchItem::STATUS_FAILED, $item->status);
        $this->assertStringContainsString('missing', $item->last_error);

        $this->assertDatabaseHas('runtime_audit_records', [
            'runtime_action_plan_id' => $dispatch->runtime_action_plan_id,
            'stage' => RuntimeAuditRecord::STAGE_EXECUTION_ADAPTER_MISSING,
        ]);
    }

    public function test_supports_false_marks_item_skipped_and_creates_audit_record(): void
    {
        $dispatch = $this->createReadyDispatch();
        $adapter = new OrchestrationFakeAdapter(
            key: 'x32',
            supportsItems: false,
            result: AdapterExecutionResult::acknowledged('x32'),
        );
        $orchestrator = $this->createOrchestrator($adapter);

        $orchestrator->execute($dispatch);

        $this->assertSame(
            RuntimeDispatchItem::STATUS_SKIPPED,
            $dispatch->runtimeDispatchItems->first()->fresh()->status,
        );
        $this->assertDatabaseHas('runtime_audit_records', [
            'runtime_action_plan_id' => $dispatch->runtime_action_plan_id,
            'stage' => RuntimeAuditRecord::STAGE_EXECUTION_ADAPTER_UNSUPPORTED,
        ]);
    }

    public function test_attempts_increments_when_adapter_execution_is_attempted(): void
    {
        $dispatch = $this->createReadyDispatch();
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $orchestrator->execute($dispatch);

        $this->assertSame(1, $dispatch->runtimeDispatchItems->first()->fresh()->attempts);
    }

    public function test_attempts_does_not_increment_when_adapter_is_missing(): void
    {
        $dispatch = $this->createReadyDispatch(adapterKey: 'missing');
        $orchestrator = $this->createOrchestrator();

        $orchestrator->execute($dispatch);

        $this->assertSame(0, $dispatch->runtimeDispatchItems->first()->fresh()->attempts);
    }

    public function test_runtime_dispatch_becomes_completed_when_all_items_acknowledged_or_skipped(): void
    {
        $dispatch = $this->createReadyDispatch(itemCount: 2);
        $registry = new AdapterRegistry;
        $registry->register($this->createAcknowledgingAdapter('x32'));
        $registry->register(new OrchestrationFakeAdapter(
            key: 'lighting',
            supportsItems: false,
            result: AdapterExecutionResult::acknowledged('lighting'),
        ));

        $dispatch->runtimeDispatchItems()->orderBy('sort_order')->get()->each(function ($item, int $index) {
            if ($index === 1) {
                $item->update(['adapter_key' => 'lighting']);
            }
        });

        $summary = (new RuntimeExecutionOrchestrator(
            app(AdapterExecutionRequestFactory::class),
            $registry,
        ))->execute($dispatch->fresh(['runtimeDispatchItems']));

        $this->assertSame(RuntimeDispatch::STATUS_COMPLETED, $summary->status);
        $this->assertSame(1, $summary->acknowledgedCount);
        $this->assertSame(1, $summary->skippedCount);
        $this->assertSame(0, $summary->failedCount);
    }

    public function test_runtime_dispatch_becomes_failed_when_any_item_fails(): void
    {
        $dispatch = $this->createReadyDispatch();
        $adapter = new OrchestrationFakeAdapter(
            key: 'x32',
            supportsItems: true,
            result: AdapterExecutionResult::failed('x32', 'Failure.'),
        );
        $orchestrator = $this->createOrchestrator($adapter);

        $summary = $orchestrator->execute($dispatch);

        $this->assertSame(RuntimeDispatch::STATUS_FAILED, $summary->status);
        $this->assertSame(RuntimeDispatch::STATUS_FAILED, $dispatch->fresh()->status);
    }

    public function test_non_ready_runtime_dispatch_cannot_be_orchestrated(): void
    {
        $dispatch = $this->createReadyDispatch();
        $dispatch->update(['status' => RuntimeDispatch::STATUS_COMPLETED]);
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Runtime execution can only be orchestrated for a ready RuntimeDispatch.');

        $orchestrator->execute($dispatch->fresh(['runtimeDispatchItems']));
    }

    public function test_audit_records_are_created_for_start_item_result_and_final_outcome(): void
    {
        $dispatch = $this->createReadyDispatch();
        $orchestrator = $this->createOrchestrator(
            $this->createAcknowledgingAdapter('x32'),
        );

        $orchestrator->execute($dispatch);

        $stages = RuntimeAuditRecord::query()
            ->where('runtime_action_plan_id', $dispatch->runtime_action_plan_id)
            ->pluck('stage')
            ->all();

        $this->assertContains(RuntimeAuditRecord::STAGE_EXECUTION_STARTED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_EXECUTION_ITEM_ACKNOWLEDGED, $stages);
        $this->assertContains(RuntimeAuditRecord::STAGE_EXECUTION_COMPLETED, $stages);
    }

    public function test_no_real_adapter_classes_exist(): void
    {
        $this->assertFalse(class_exists(\App\Services\X32\SocketX32Transport::class));
        $this->assertFalse(class_exists(\App\Services\LightingAdapter::class));
        $this->assertFalse(class_exists(\App\Services\MusicianDeviceAdapter::class));
        $this->assertFalse(class_exists(\App\Services\VideoAdapter::class));

        $methods = get_class_methods(RuntimeExecutionOrchestrator::class);
        $this->assertNotContains('send', $methods);
        $this->assertNotContains('connect', $methods);
    }

    private function createOrchestrator(?OrchestrationFakeAdapter $adapter = null): RuntimeExecutionOrchestrator
    {
        $registry = new AdapterRegistry;

        if ($adapter !== null) {
            $registry->register($adapter);
        }

        return new RuntimeExecutionOrchestrator(
            app(AdapterExecutionRequestFactory::class),
            $registry,
        );
    }

    private function createAcknowledgingAdapter(string $key): OrchestrationFakeAdapter
    {
        return new OrchestrationFakeAdapter(
            key: $key,
            supportsItems: true,
            result: AdapterExecutionResult::acknowledged($key),
        );
    }

    private function createReadyDispatch(
        int $itemCount = 1,
        string $adapterKey = 'x32',
        array $sortOrders = [],
    ): RuntimeDispatch {
        $event = RuntimeEvent::factory()->create([
            'status' => 'planned',
        ]);

        $plan = RuntimeActionPlan::factory()->create([
            'runtime_event_id' => $event->id,
            'performance_id' => $event->performance_id,
            'status' => RuntimeActionPlan::STATUS_READY,
        ]);

        $dispatch = RuntimeDispatch::factory()->create([
            'runtime_action_plan_id' => $plan->id,
            'performance_id' => $plan->performance_id,
            'status' => RuntimeDispatch::STATUS_READY,
        ]);

        for ($i = 0; $i < $itemCount; $i++) {
            $actionItem = RuntimeActionItem::factory()->create([
                'runtime_action_plan_id' => $plan->id,
                'action_type_code' => 'X32_SCENE',
                'sort_order' => $sortOrders[$i] ?? (($i + 1) * 10),
            ]);

            RuntimeDispatchItem::factory()->create([
                'runtime_dispatch_id' => $dispatch->id,
                'runtime_action_item_id' => $actionItem->id,
                'adapter_key' => $adapterKey,
                'action_type_code' => 'X32_SCENE',
                'sort_order' => $sortOrders[$i] ?? (($i + 1) * 10),
                'status' => RuntimeDispatchItem::STATUS_READY,
                'attempts' => 0,
            ]);
        }

        return $dispatch->fresh(['runtimeDispatchItems', 'runtimeActionPlan.runtimeEvent']);
    }
}

class OrchestrationFakeAdapter implements RuntimeAdapterInterface
{
    /** @var list<int> */
    public static array $processedItemIds = [];

    public function __construct(
        private readonly string $key,
        private readonly bool $supportsItems,
        private readonly AdapterExecutionResult $result,
    ) {}

    public function adapterKey(): string
    {
        return $this->key;
    }

    public function supports(\App\Models\RuntimeDispatchItem $item): bool
    {
        return $this->supportsItems && $item->adapter_key === $this->key;
    }

    public function execute(AdapterExecutionRequest $request): AdapterExecutionResult
    {
        self::$processedItemIds[] = $request->runtimeDispatchItemId;

        return $this->result;
    }
}
