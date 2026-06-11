<?php

namespace App\Services\Runtime;

use App\Exceptions\Runtime\AdapterNotFoundException;
use App\Exceptions\Runtime\UnsupportedDispatchItemException;
use App\Models\RuntimeAuditRecord;
use App\Models\RuntimeDispatch;
use App\Models\RuntimeDispatchItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RuntimeExecutionOrchestrator
{
    public function __construct(
        private readonly AdapterExecutionRequestFactory $requestFactory,
        private readonly AdapterRegistry $adapterRegistry,
    ) {}

    public function execute(RuntimeDispatch $runtimeDispatch): RuntimeExecutionSummary
    {
        $runtimeDispatch->loadMissing([
            'runtimeDispatchItems',
            'runtimeActionPlan.runtimeEvent',
        ]);

        if ($runtimeDispatch->status !== RuntimeDispatch::STATUS_READY) {
            throw new InvalidArgumentException(
                'Runtime execution can only be orchestrated for a ready RuntimeDispatch.',
            );
        }

        return DB::transaction(function () use ($runtimeDispatch) {
            $runtimeDispatch->update(['status' => RuntimeDispatch::STATUS_DISPATCHING]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_STARTED,
                message: 'Runtime dispatch execution started.',
                context: [
                    'runtime_dispatch_id' => $runtimeDispatch->id,
                    'item_count' => $runtimeDispatch->runtimeDispatchItems->count(),
                ],
            );

            $results = [];
            $acknowledgedCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            foreach ($runtimeDispatch->runtimeDispatchItems as $item) {
                $itemResult = $this->processItem($runtimeDispatch, $item);
                $results[] = $itemResult;

                match ($itemResult['item_status']) {
                    RuntimeDispatchItem::STATUS_ACKNOWLEDGED => $acknowledgedCount++,
                    RuntimeDispatchItem::STATUS_FAILED => $failedCount++,
                    RuntimeDispatchItem::STATUS_SKIPPED => $skippedCount++,
                    default => null,
                };
            }

            $finalStatus = $failedCount > 0
                ? RuntimeDispatch::STATUS_FAILED
                : RuntimeDispatch::STATUS_COMPLETED;

            $runtimeDispatch->update(['status' => $finalStatus]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: $failedCount > 0
                    ? RuntimeAuditRecord::STAGE_EXECUTION_FAILED
                    : RuntimeAuditRecord::STAGE_EXECUTION_COMPLETED,
                message: $failedCount > 0
                    ? 'Runtime dispatch execution completed with failures.'
                    : 'Runtime dispatch execution completed successfully.',
                context: [
                    'runtime_dispatch_id' => $runtimeDispatch->id,
                    'acknowledged_count' => $acknowledgedCount,
                    'failed_count' => $failedCount,
                    'skipped_count' => $skippedCount,
                    'final_status' => $finalStatus,
                ],
            );

            return new RuntimeExecutionSummary(
                runtimeDispatchId: $runtimeDispatch->id,
                status: $finalStatus,
                itemCount: count($results),
                acknowledgedCount: $acknowledgedCount,
                failedCount: $failedCount,
                skippedCount: $skippedCount,
                results: $results,
            );
        });
    }

    /**
     * @return array{
     *     runtime_dispatch_item_id: int,
     *     adapter_key: string,
     *     item_status: string,
     *     result_status: ?string
     * }
     */
    private function processItem(RuntimeDispatch $runtimeDispatch, RuntimeDispatchItem $item): array
    {
        if (! $this->adapterRegistry->has($item->adapter_key)) {
            $error = AdapterNotFoundException::forKey($item->adapter_key)->getMessage();

            $item->update([
                'status' => RuntimeDispatchItem::STATUS_FAILED,
                'last_error' => $error,
            ]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ADAPTER_MISSING,
                message: 'Runtime adapter missing for dispatch item.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'adapter_key' => $item->adapter_key,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ITEM_FAILED,
                message: 'Runtime dispatch item failed due to missing adapter.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'last_error' => $error,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            return $this->itemResult($item, null);
        }

        $adapter = $this->adapterRegistry->resolve($item->adapter_key);

        if (! $adapter->supports($item)) {
            $error = UnsupportedDispatchItemException::forAdapter($item->adapter_key, $item->id)->getMessage();

            $item->update([
                'status' => RuntimeDispatchItem::STATUS_SKIPPED,
                'last_error' => $error,
            ]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ADAPTER_UNSUPPORTED,
                message: 'Runtime adapter does not support dispatch item.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'adapter_key' => $item->adapter_key,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ITEM_SKIPPED,
                message: 'Runtime dispatch item skipped as unsupported.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'last_error' => $error,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            return $this->itemResult($item, null);
        }

        $request = $this->requestFactory->fromDispatchItem($item);
        $item->increment('attempts');

        $adapterResult = $adapter->execute($request);

        return $this->applyAdapterResult($runtimeDispatch, $item, $adapterResult);
    }

    /**
     * @return array{
     *     runtime_dispatch_item_id: int,
     *     adapter_key: string,
     *     item_status: string,
     *     result_status: ?string
     * }
     */
    private function applyAdapterResult(
        RuntimeDispatch $runtimeDispatch,
        RuntimeDispatchItem $item,
        AdapterExecutionResult $adapterResult,
    ): array {
        if ($adapterResult->status === AdapterExecutionResult::STATUS_ACKNOWLEDGED) {
            $item->update([
                'status' => RuntimeDispatchItem::STATUS_ACKNOWLEDGED,
                'last_error' => null,
            ]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ITEM_ACKNOWLEDGED,
                message: 'Runtime dispatch item acknowledged by adapter contract.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'adapter_key' => $item->adapter_key,
                    'result_status' => $adapterResult->status,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            return $this->itemResult($item, $adapterResult->status);
        }

        if ($adapterResult->status === AdapterExecutionResult::STATUS_FAILED) {
            $item->update([
                'status' => RuntimeDispatchItem::STATUS_FAILED,
                'last_error' => $adapterResult->message,
            ]);

            $this->recordAudit(
                runtimeDispatch: $runtimeDispatch,
                stage: RuntimeAuditRecord::STAGE_EXECUTION_ITEM_FAILED,
                message: 'Runtime dispatch item failed during adapter execution.',
                context: [
                    'runtime_dispatch_item_id' => $item->id,
                    'adapter_key' => $item->adapter_key,
                    'result_status' => $adapterResult->status,
                    'last_error' => $adapterResult->message,
                ],
                runtimeActionItemId: $item->runtime_action_item_id,
            );

            return $this->itemResult($item, $adapterResult->status);
        }

        $item->update([
            'status' => RuntimeDispatchItem::STATUS_SKIPPED,
            'last_error' => $adapterResult->message,
        ]);

        $this->recordAudit(
            runtimeDispatch: $runtimeDispatch,
            stage: RuntimeAuditRecord::STAGE_EXECUTION_ITEM_SKIPPED,
            message: 'Runtime dispatch item skipped by adapter contract result.',
            context: [
                'runtime_dispatch_item_id' => $item->id,
                'adapter_key' => $item->adapter_key,
                'result_status' => $adapterResult->status,
                'last_error' => $adapterResult->message,
            ],
            runtimeActionItemId: $item->runtime_action_item_id,
        );

        return $this->itemResult($item, $adapterResult->status);
    }

    /**
     * @return array{
     *     runtime_dispatch_item_id: int,
     *     adapter_key: string,
     *     item_status: string,
     *     result_status: ?string
     * }
     */
    private function itemResult(RuntimeDispatchItem $item, ?string $resultStatus): array
    {
        return [
            'runtime_dispatch_item_id' => $item->id,
            'adapter_key' => $item->adapter_key,
            'item_status' => $item->fresh()->status,
            'result_status' => $resultStatus,
        ];
    }

    private function recordAudit(
        RuntimeDispatch $runtimeDispatch,
        string $stage,
        string $message,
        ?array $context = null,
        ?int $runtimeActionItemId = null,
    ): void {
        $plan = $runtimeDispatch->runtimeActionPlan;

        RuntimeAuditRecord::create([
            'runtime_event_id' => $plan?->runtimeEvent?->id,
            'runtime_action_plan_id' => $plan?->id,
            'runtime_action_item_id' => $runtimeActionItemId,
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
